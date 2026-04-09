import uuid
import logging
from typing import List, Annotated # Annotated is the secret sauce here
from fastapi import APIRouter, Depends, HTTPException, UploadFile, File, status
from fastapi.security import OAuth2PasswordBearer, OAuth2PasswordRequestForm
from sqlalchemy.orm import Session
from jose import jwt, JWTError

from app.db.mysql import get_db
from app.models.database import User, ChatSession, ChatMessage
from app.models.schemas import QuestionRequest
from app.core import security
from app.services import rag
from app.core.logging_config import logger

router = APIRouter()

# Setup for JWT Authentication
oauth2_scheme = OAuth2PasswordBearer(tokenUrl="login")

# --- AUTHENTICATION DEPENDENCY ---
def get_current_user(token: str = Depends(oauth2_scheme), db: Session = Depends(get_db)):
    credentials_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Could not validate credentials",
        headers={"WWW-Authenticate": "Bearer"},
    )
    try:
        payload = jwt.decode(token, security.SECRET_KEY, algorithms=[security.ALGORITHM])
        username: str = payload.get("sub")
        if username is None:
            raise credentials_exception
    except JWTError:
        raise credentials_exception
        
    user = db.query(User).filter(User.username == username).first()
    if user is None:
        raise credentials_exception
    return user

# --- USER AUTHENTICATION ENDPOINTS ---

@router.post("/signup")
def signup(form_data: OAuth2PasswordRequestForm = Depends(), db: Session = Depends(get_db)):
    existing_user = db.query(User).filter(User.username == form_data.username).first()
    if existing_user:
        raise HTTPException(status_code=400, detail="Username already registered")
    
    hashed_pass = security.get_password_hash(form_data.password)
    new_user = User(username=form_data.username, hashed_password=hashed_pass)
    db.add(new_user)
    db.commit()
    return {"message": "User created successfully. You can now login."}

@router.post("/login")
def login(form_data: OAuth2PasswordRequestForm = Depends(), db: Session = Depends(get_db)):
    user = db.query(User).filter(User.username == form_data.username).first()
    if not user or not security.verify_password(form_data.password, user.hashed_password):
        raise HTTPException(status_code=401, detail="Incorrect username or password")
    
    access_token = security.create_access_token(data={"sub": user.username})
    return {"access_token": access_token, "token_type": "bearer"}

# --- PDF & RAG ENDPOINTS ---

@router.post("/upload")
async def upload_pdf(
    # Using Annotated[UploadFile, File(...)] for single upload
    file: Annotated[UploadFile, File(description="Standard PDF upload")], 
    user: User = Depends(get_current_user), 
    db: Session = Depends(get_db)
):
    if not file.filename.lower().endswith(".pdf"):
        raise HTTPException(status_code=400, detail="Only PDF files are allowed")

    session_id = str(uuid.uuid4())
    try:
        result = await rag.process_and_store_pdf(file, session_id, user.id, db)
        return result
    except Exception as e:
        logger.error(f"Upload Error: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/ask")
async def ask_question(
    request: QuestionRequest, 
    user: User = Depends(get_current_user), 
    db: Session = Depends(get_db)
):
    session = db.query(ChatSession).filter(
        ChatSession.session_id == request.session_id, 
        ChatSession.user_id == user.id
    ).first()
    
    if not session:
        raise HTTPException(status_code=403, detail="Not authorized to access this session")

    try:
        result = rag.answer_question(request.session_id, request.question, db)
        return result
    except Exception as e:
        logger.error(f"Ask Error: {e}")
        raise HTTPException(status_code=500, detail=str(e))

# --- SIDEBAR & HISTORY ENDPOINTS ---

@router.get("/sidebar/history")
def get_sidebar_history(user: User = Depends(get_current_user), db: Session = Depends(get_db)):
    sessions = db.query(ChatSession).filter(ChatSession.user_id == user.id).order_by(ChatSession.created_at.desc()).all()
    return [{"session_id": s.session_id, "filename": s.filename, "created_at": s.created_at} for s in sessions]

@router.get("/chat/{session_id}/messages")
def get_chat_messages(session_id: str, user: User = Depends(get_current_user), db: Session = Depends(get_db)):
    session = db.query(ChatSession).filter(ChatSession.session_id == session_id, ChatSession.user_id == user.id).first()
    if not session:
        raise HTTPException(status_code=403, detail="Access denied")

    messages = db.query(ChatMessage).filter(ChatMessage.session_id == session_id).order_by(ChatMessage.created_at.asc()).all()
    return [{"role": m.role, "content": m.content, "timestamp": m.created_at} for m in messages]

# --- MULTI-PDF COMPARISON & SUPPLEMENTARY UPLOAD ---

@router.post("/compare/upload")
async def upload_multiple_pdfs(
    # Annotated[List[UploadFile], File(...)] is the ONLY way to guarantee the upload button for arrays
    files: Annotated[List[UploadFile], File(description="Select multiple PDFs to compare")], 
    user: User = Depends(get_current_user), 
    db: Session = Depends(get_db)
):
    if len(files) < 2:
        raise HTTPException(status_code=400, detail="Please upload at least 2 PDFs to compare.")

    for file in files:
        if not file.filename.lower().endswith(".pdf"):
             raise HTTPException(status_code=400, detail=f"File {file.filename} is not a PDF")

    session_id = str(uuid.uuid4())
    try:
        result = await rag.process_comparison_pdfs(files, session_id, user.id, db)
        return result
    except Exception as e:
        logger.error(f"Comparison Error: {e}")
        raise HTTPException(status_code=500, detail="Comparison upload failed")
