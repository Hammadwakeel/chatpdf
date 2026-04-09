from qdrant_client import QdrantClient
from qdrant_client.http import models
from app.core.config import settings
from app.core.logging_config import logger

qdrant_client = QdrantClient(
    url=settings.qdrant_url,
    api_key=settings.qdrant_api_key,
)

COLLECTION_NAME = "pdf_chat_sessions"

def init_qdrant():
    try:
        # 1. Get existing collections
        collections = qdrant_client.get_collections().collections
        exists = any(c.name == COLLECTION_NAME for c in collections)

        if not exists:
            logger.info(f"🚀 Creating collection: {COLLECTION_NAME}")
            qdrant_client.create_collection(
                collection_name=COLLECTION_NAME,
                vectors_config=models.VectorParams(
                    size=384, # Dimensions for FastEmbed
                    distance=models.Distance.COSINE
                ),
            )
        
        # 2. Force Check for the Payload Index
        # We try to create it; if it already exists, Qdrant will just ignore the request
        logger.info(f"⚡ Verifying Payload Index for 'metadata.session_id'...")
        qdrant_client.create_payload_index(
            collection_name=COLLECTION_NAME,
            field_name="metadata.session_id",
            field_schema=models.PayloadSchemaType.KEYWORD,
        )
        logger.info("✅ Qdrant is fully indexed and ready.")

    except Exception as e:
        logger.error(f"❌ Qdrant Init Error: {e}")
        # We don't raise here so the app can still try to start, 
        # but keep an eye on the logs!