#!/bin/bash

echo "🚀 Starting Master Setup for ChatPDF..."

# --- 1. Backend Setup ---
echo "🐍 Setting up Python Backend..."
if [ -d "backend" ]; then
    cd backend
    python3 -m venv venv
    source venv/bin/activate
    pip install --upgrade pip
    if [ -f "requirements.txt" ]; then
        pip install -r requirements.txt
    fi
    if [ ! -f ".env" ]; then cp .env.example .env 2>/dev/null || touch .env; fi
    cd ..
else
    echo "⚠️  Backend folder not found, skipping..."
fi

# --- 2. Frontend Setup ---
echo "📦 Setting up Laravel Frontend..."
if [ -d "rag-frontend" ]; then
    cd rag-frontend
    composer install
    if [ ! -f ".env" ]; then
        cp .env.example .env
        echo "FASTAPI_URL=http://127.0.0.1:8000" >> .env
        echo "OCR_API_URL=https://hammad712-urdu-ocr-app.hf.space/upload" >> .env
    fi
    mkdir -p database
    touch database/database.sqlite
    php artisan key:generate
    php artisan migrate --force
    cd ..
else
    echo "⚠️  Frontend folder not found, skipping..."
fi

echo "✅ ALL DONE!"
echo "1. Run Backend: cd backend && source venv/bin/activate && uvicorn main:app --reload"
echo "2. Run Frontend: cd rag-frontend && php artisan serve --port=8001"
