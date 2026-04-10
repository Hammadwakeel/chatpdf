import os
import shutil
from pathlib import Path
from typing import List, TypedDict, Optional
from fastapi import UploadFile
from pydantic import BaseModel, Field

# LangGraph & LangChain
from langgraph.graph import StateGraph, END
from langchain_core.messages import BaseMessage, HumanMessage, AIMessage
from langchain_core.prompts import ChatPromptTemplate, MessagesPlaceholder
from langchain_community.embeddings.fastembed import FastEmbedEmbeddings
from langchain_groq import ChatGroq
from langchain_qdrant import QdrantVectorStore
from qdrant_client.http import models as qdrant_models

# Tenacity for Retry Logic
from tenacity import retry, stop_after_attempt, wait_exponential

# Database & Document Processing
from sqlalchemy.orm import Session
from langchain_community.document_loaders import PyPDFLoader
from langchain_text_splitters import RecursiveCharacterTextSplitter

# Internal Imports
from app.core.config import settings
from app.db.qdrant import qdrant_client, COLLECTION_NAME
from app.models.database import ChatMessage, ChatSession
from app.core.logging_config import logger

# ==========================================
# 1. Structured Output Schema & Storage Setup
# ==========================================

# Directory to store PDFs so the FastAPI StaticFiles can serve them to the frontend viewer
PDF_STORAGE_DIR = Path("storage/pdfs")
PDF_STORAGE_DIR.mkdir(parents=True, exist_ok=True)

class SuggestedQuestions(BaseModel):
    """Schema to force the Llama model to return a structured JSON array of questions."""
    questions: List[str] = Field(description="A list of 3 highly relevant questions the user could ask about this document.")

# ==========================================
# 2. State Definition
# ==========================================
class GraphState(TypedDict):
    session_id: str
    db: Session
    input_question: str
    standalone_question: str
    chat_history: List[BaseMessage]
    summary: str
    context_text: str  # Formatted string containing chunks with their sources
    answer: str

# ==========================================
# 3. Model Preloading Logic
# ==========================================
_models = {
    "embeddings": None,
    "llm": None
}

def preload_models():
    """Warms up the AI models. Called by the FastAPI lifespan on startup."""
    try:
        if _models["embeddings"] is None:
            logger.info("📡 Loading FastEmbed model (BAAI/bge-small-en-v1.5)...")
            _models["embeddings"] = FastEmbedEmbeddings(model_name="BAAI/bge-small-en-v1.5")
        
        if _models["llm"] is None:
            logger.info("📡 Initializing Groq LLM (llama-3.3-70b-versatile)...")
            _models["llm"] = ChatGroq(
                model_name="llama-3.3-70b-versatile", 
                temperature=0, 
                api_key=settings.groq_api_key
            )
        logger.info("✅ All models preloaded successfully.")
    except Exception as e:
        logger.error(f"❌ Failed to preload models: {e}")
        raise

def get_embeddings():
    if _models["embeddings"] is None:
        preload_models()
    return _models["embeddings"]

def get_llm():
    if _models["llm"] is None:
        preload_models()
    return _models["llm"]

# ==========================================
# 4. Database & History Helpers
# ==========================================
def fetch_history(db: Session, session_id: str, limit: int = 10):
    msgs = db.query(ChatMessage).filter(ChatMessage.session_id == session_id)\
             .order_by(ChatMessage.created_at.desc()).limit(limit).all()
    formatted = []
    for m in reversed(msgs):
        if m.role == "human":
            formatted.append(HumanMessage(content=m.content))
        else:
            formatted.append(AIMessage(content=m.content))
    return formatted

def save_msg(db: Session, session_id: str, role: str, content: str):
    try:
        db.add(ChatMessage(session_id=session_id, role=role, content=content))
        db.commit()
    except Exception as e:
        logger.error(f"❌ Database error while saving message: {e}")
        db.rollback()

# ==========================================
# 5. Question Suggestion Generation
# ==========================================
@retry(stop=stop_after_attempt(3), wait=wait_exponential(multiplier=1, min=2, max=10))
def generate_pdf_suggestions(text_context: str) -> List[str]:
    """Generates 3 suggested questions using Llama 3.3 with Structured Output."""
    llm = get_llm()
    # Bind the Pydantic schema to the model
    structured_llm = llm.with_structured_output(SuggestedQuestions)
    
    prompt = f"Analyze this document snippet and suggest exactly 3 important questions a user might ask about it:\n\n{text_context[:5000]}"
    result = structured_llm.invoke(prompt)
    
    return result.questions

# ==========================================
# 6. Ingestion Services (Single & Multi-PDF)
# ==========================================

async def process_and_store_pdf(file: UploadFile, session_id: str, user_id: int, db: Session):
    """Wrapper for single PDF upload."""
    return await process_comparison_pdfs([file], session_id, user_id, db, is_comparison=False)

async def process_comparison_pdfs(files: List[UploadFile], session_id: str, user_id: int, db: Session, is_comparison: bool = True):
    """Ingests PDFs, saves them for viewing, generates suggestions, and indexes chunks."""
    logger.info(f"📂 Processing {len(files)} PDFs for session: {session_id}")
    
    all_splits = []
    file_names = []
    full_text_for_suggestions = ""

    for file in files:
        file_names.append(file.filename)
        
        # Save the file permanently so the frontend PDF viewer can access it
        file_path = PDF_STORAGE_DIR / file.filename
        with open(file_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)

        try:
            loader = PyPDFLoader(str(file_path))
            docs = loader.load()
            
            # Accumulate some text to generate the suggested questions later
            if len(full_text_for_suggestions) < 5000:
                full_text_for_suggestions += "\n".join([d.page_content for d in docs[:3]])

            # Use slightly smaller chunks for better comparison granularity
            text_splitter = RecursiveCharacterTextSplitter(chunk_size=700, chunk_overlap=100)
            splits = text_splitter.split_documents(docs)
            
            # Tag each chunk with the session AND the specific filename
            for split in splits:
                split.metadata["session_id"] = session_id
                split.metadata["source"] = file.filename
            
            all_splits.extend(splits)
        except Exception as e:
            logger.error(f"❌ Error parsing PDF {file.filename}: {e}")

    # Store in Qdrant
    vector_store = QdrantVectorStore(
        client=qdrant_client,
        collection_name=COLLECTION_NAME,
        embedding=get_embeddings()
    )
    vector_store.add_documents(all_splits)
    logger.info(f"✅ Indexed {len(all_splits)} chunks from {len(files)} files.")
    
    # Generate suggested questions from the extracted text
    suggestions = []
    try:
        suggestions = generate_pdf_suggestions(full_text_for_suggestions)
    except Exception as e:
        logger.error(f"⚠️ Failed to generate suggestions: {e}")
    
    # Store the session in MySQL
    # FIXED: Removed the [:50]... truncation to ensure exact file names are preserved for the PDF viewer
    display_name = file_names[0] if not is_comparison else f"Comparison: {', '.join(file_names)}"
    
    # We save the suggestions into the summary column initially so we can retrieve them easily
    initial_summary = "Suggested: " + "|".join(suggestions) if suggestions else "New conversation."
    
    new_session = ChatSession(session_id=session_id, user_id=user_id, filename=display_name, summary=initial_summary)
    db.add(new_session)
    db.commit()
    
    return {"session_id": session_id, "files_processed": file_names, "suggestions": suggestions}

# ==========================================
# 7. LangGraph Nodes
# ==========================================

@retry(stop=stop_after_attempt(3), wait=wait_exponential(multiplier=1, min=2, max=10))
def contextualize_node(state: GraphState):
    """Rephrases input based on history with retry logic."""
    if not state["chat_history"]:
        return {"standalone_question": state["input_question"]}
    
    llm = get_llm()
    prompt = f"Given history, rephrase this as a standalone question: {state['chat_history']}\nQuestion: {state['input_question']}"
    res = llm.invoke(prompt)
    return {"standalone_question": res.content}

def search_node(state: GraphState):
    """Retrieves chunks and labels them with their source files."""
    logger.info(f"🔎 Node: Comparative Search for '{state['standalone_question'][:50]}...'")
    vector_store = QdrantVectorStore(
        client=qdrant_client, 
        collection_name=COLLECTION_NAME, 
        embedding=get_embeddings()
    )
    
    session_filter = qdrant_models.Filter(
        must=[qdrant_models.FieldCondition(
            key="metadata.session_id", 
            match=qdrant_models.MatchValue(value=state['session_id'])
        )]
    )
    
    # Retrieve top 10 for better coverage across multiple files
    docs = vector_store.similarity_search(state["standalone_question"], k=10, filter=session_filter)
    
    # Format context with explicit Source labels
    formatted_context = ""
    for d in docs:
        source = d.metadata.get("source", "Unknown Document")
        formatted_context += f"\n--- SOURCE: {source} ---\n{d.page_content}\n"
        
    return {"context_text": formatted_context}

@retry(stop=stop_after_attempt(3), wait=wait_exponential(multiplier=1, min=2, max=10))
def generate_node(state: GraphState):
    """Generates comparative or single-file answers with retry logic."""
    logger.info("🧠 Node: Generating Comparative Answer")
    llm = get_llm()
    
    sys_msg = f"""You are a Precise Comparative AI Assistant. 
    Use the context below to answer accurately. 
    If multiple documents are referenced in the context, compare their information, 
    highlighting similarities, contradictions, or unique data points.
    Always mention the source filename when providing information.
    
    Summary of previous conversation: {state['summary']}
    
    Context from documents:
    {state['context_text']}
    """
    
    prompt = ChatPromptTemplate.from_messages([
        ("system", sys_msg),
        MessagesPlaceholder("chat_history"),
        ("human", "{input}"),
    ])
    
    response = (prompt | llm).invoke({
        "input": state["standalone_question"], 
        "chat_history": state["chat_history"]
    })
    
    # Save history
    save_msg(state["db"], state["session_id"], "human", state["input_question"])
    save_msg(state["db"], state["session_id"], "ai", response.content)
    
    return {"answer": response.content}

@retry(stop=stop_after_attempt(3), wait=wait_exponential(multiplier=1, min=2, max=10))
def summarize_node(state: GraphState):
    """Updates the summary with retry logic."""
    if len(state["chat_history"]) < 4:
        return {}

    llm = get_llm()
    hist_str = "\n".join([f"{m.type}: {m.content}" for m in state["chat_history"][-2:]])
    prompt = f"Update the summary: {state['summary']}\nRecent exchanges: {hist_str}\nConsolidated Summary:"
    new_sum = llm.invoke(prompt).content
    
    state["db"].query(ChatSession).filter(ChatSession.session_id == state["session_id"]).update({"summary": new_sum})
    state["db"].commit()
    
    return {"summary": new_sum}

# ==========================================
# 8. Graph Compilation
# ==========================================
builder = StateGraph(GraphState)

builder.add_node("context", contextualize_node)
builder.add_node("search", search_node)
builder.add_node("generate", generate_node)
builder.add_node("summarize", summarize_node)

builder.set_entry_point("context")
builder.add_edge("context", "search")
builder.add_edge("search", "generate")
builder.add_edge("generate", "summarize")
builder.add_edge("summarize", END)

rag_graph = builder.compile()

# ==========================================
# 9. Final Entry Point
# ==========================================
def answer_question(session_id: str, question: str, db: Session):
    session_data = db.query(ChatSession).filter(ChatSession.session_id == session_id).first()
    if not session_data:
        raise ValueError("Session not found.")
        
    history = fetch_history(db, session_id)
    
    initial_state = {
        "session_id": session_id,
        "db": db,
        "input_question": question,
        "chat_history": history,
        "summary": session_data.summary or "New conversation.",
        "context_text": ""
    }
    
    result = rag_graph.invoke(initial_state)
    return {
        "answer": result["answer"],
        "summary": result.get("summary", session_data.summary)
    }