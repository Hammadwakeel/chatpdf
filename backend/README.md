# Quanti Axionix - Agentic RAG Backend

This is the high-performance AI engine for Quanti Axionix, a sophisticated ChatPDF platform. It utilizes an agentic workflow to handle single-document Q&A, multi-document comparison, and automated document intelligence.

## 🧠 Core Architecture: Agentic RAG
Unlike traditional linear RAG pipelines, this backend implements an **Agentic Workflow** using **LangGraph**. Every query passes through a state machine that intelligently contextualizes, searches, and synthesizes information.

### The LangGraph State Machine:
1.  **Contextualize**: Rephrases user queries into standalone questions based on chat history.
2.  **Comparative Search**: Performs filtered vector searches in **Qdrant**, labeling chunks with their specific source filenames.
3.  **Synthesize (Llama 3.3)**: Generates a final response. If multiple sources are present, it performs a comparative analysis, highlighting contradictions or unique points.
4.  **Summarize**: Dynamically updates the conversation summary to maintain long-term context.



---

## 🛠️ Technology Stack
| Component | Technology |
| :--- | :--- |
| **Framework** | FastAPI (Asynchronous Python) |
| **Orchestration** | LangGraph & LangChain |
| **LLM** | Llama 3.3 70B Versatile (via Groq) |
| **Embeddings** | FastEmbed (BAAI/bge-small-en-v1.5) |
| **Vector Store** | Qdrant (Cloud/Local) |
| **Database** | MySQL (SQLAlchemy ORM) |
| **Logic Safety** | Tenacity (Exponential Backoff Retries) |

---

## ✨ Advanced Features
* **Structured Output**: Uses Pydantic schemas to force the LLM to return valid JSON for suggested follow-up questions.
* **Source Attribution**: Every answer explicitly cites the filename it retrieved information from.
* **Multi-PDF Comparison**: Specialized ingestion that tags chunks by `session_id` and `source_file` for isolated comparative retrieval.
* **Resilience**: Built-in retry mechanisms for LLM calls to handle rate-limiting or network fluctuations.
* **Static Asset Serving**: Native PDF serving to allow frontend iframe viewing.

---

## 📂 Project Structure
\`\`\`text
backend/
├── app/
│   ├── api/          # FastAPI Routes (Auth, RAG, History)
│   ├── core/         # Security, Config, Logging
│   ├── db/           # MySQL & Qdrant Connection Logic
│   ├── models/       # SQLAlchemy & Pydantic Schemas
│   └── services/     # The "Brain" (LangGraph & RAG Logic)
├── storage/
│   └── pdfs/         # Locally stored PDFs for the viewer
└── main.py           # Lifespan management & App Entry
\`\`\`

---

## ⚙️ Installation & Setup

### 1. Environment Setup
Create a \`.env\` file in the root:
\`\`\`env
GROQ_API_KEY=your_groq_key
DATABASE_URL=mysql+pymysql://user:pass@localhost/db_name
QDRANT_URL=your_qdrant_url
QDRANT_API_KEY=your_qdrant_key
SECRET_KEY=your_jwt_secret
\`\`\`

### 2. Install Dependencies
\`\`\`bash
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
\`\`\`

### 3. Run the Application
\`\`\`bash
uvicorn app.main:app --reload
\`\`\`

---

## 📡 API Endpoints (Quick Reference)
* \`POST /upload\`: Ingest single PDF and generate suggested questions.
* \`POST /compare/upload\`: Ingest 2-5 PDFs for comparative RAG.
* \`POST /ask\`: Execute the LangGraph RAG pipeline.
* \`POST /suggested-questions\`: Retrieve pre-generated questions for a session.
* \`GET /pdfs/{filename}\`: Serve raw PDF for the frontend viewer.

---

Developed with ❤️ by **Hammad Wakeel** -
