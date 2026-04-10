import os
from contextlib import asynccontextmanager
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles

from app.api.routes import router
from app.db.mysql import engine, Base
from app.db.qdrant import init_qdrant
from app.services.rag import preload_models
from app.core.logging_config import setup_logging, logger

@asynccontextmanager
async def lifespan(app: FastAPI):
    # --- STARTUP LOGIC ---
    setup_logging()
    logger.info("🚀 Starting Quanti Axionix RAG API...")

    try:
        # 0. Ensure Storage Directories Exist
        logger.info("📁 Verifying storage directories...")
        os.makedirs("storage/pdfs", exist_ok=True)
        
        # 1. Check MySQL & Create Tables
        logger.info("🔍 Checking MySQL Connection...")
        Base.metadata.create_all(bind=engine)
        logger.info("✅ MySQL Tables Verified.")

        # 2. Check Qdrant
        logger.info("🔍 Checking Qdrant Collection...")
        init_qdrant()
        logger.info("✅ Qdrant Initialized.")

        # 3. Preload AI Models into RAM
        logger.info("🤖 Preloading AI Models (FastEmbed & Groq Llama 3.3)...")
        preload_models()
        logger.info("✅ Models Loaded and Ready.")

    except Exception as e:
        logger.error(f"❌ Startup Failed: {str(e)}")
        # If critical systems fail, we stop the app
        raise SystemExit(1)

    yield
    # --- SHUTDOWN LOGIC ---
    logger.info("🛑 Shutting down Application...")

# Initialize FastAPI App
app = FastAPI(title="Quanti Axionix API", lifespan=lifespan)

# --- CORS MIDDLEWARE ---
# This allows your Laravel frontend (port 8001) to communicate with this API (port 8000)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"], # In production, change "*" to ["http://127.0.0.1:8001", "http://localhost:8001"]
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# --- STATIC FILES MOUNT ---
# This safely exposes the local PDFs folder to the web so the Laravel PDF Viewer can display them
app.mount("/pdfs", StaticFiles(directory="storage/pdfs"), name="pdfs")

# --- ROUTER REGISTRATION ---
app.include_router(router)