from contextlib import asynccontextmanager
from fastapi import FastAPI
from app.api.routes import router
from app.db.mysql import engine, Base
from app.db.qdrant import init_qdrant
from app.services.rag import preload_models
from app.core.logging_config import setup_logging, logger

@asynccontextmanager
async def lifespan(app: FastAPI):
    # --- STARTUP LOGIC ---
    setup_logging()
    logger.info("🚀 Starting RAG Application...")

    try:
        # 1. Check MySQL & Create Tables
        logger.info("🔍 Checking MySQL Connection...")
        Base.metadata.create_all(bind=engine)
        logger.info("✅ MySQL Tables Verified.")

        # 2. Check Qdrant
        logger.info("🔍 Checking Qdrant Collection...")
        init_qdrant()
        logger.info("✅ Qdrant Initialized.")

        # 3. Preload AI Models into RAM
        logger.info("🤖 Preloading AI Models (FastEmbed & Groq)...")
        preload_models()
        logger.info("✅ Models Loaded and Ready.")

    except Exception as e:
        logger.error(f"❌ Startup Failed: {str(e)}")
        # If critical systems fail, we stop the app
        raise SystemExit(1)

    yield
    # --- SHUTDOWN LOGIC ---
    logger.info("🛑 Shutting down Application...")

app = FastAPI(title="Production RAG API", lifespan=lifespan)
app.include_router(router)