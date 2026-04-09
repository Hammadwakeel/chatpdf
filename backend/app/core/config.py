from pydantic_settings import BaseSettings

class Settings(BaseSettings):
    groq_api_key: str
    voyage_api_key: str
    qdrant_url: str
    qdrant_api_key: str
    mysql_url: str

    class Config:
        env_file = ".env"

settings = Settings()