# Full-Stack Agentic ChatPDF Ecosystem

An advanced, production-ready AI platform designed for deep interaction with PDF documents. This ecosystem integrates a **Laravel-based orchestration dashboard** with a **FastAPI-powered Agentic RAG backend**, featuring Llama 3.3, OCR, and multi-document comparative analysis.

---

## 🏗️ Ecosystem Architecture

* **`/backend` (The Brain)**: A Python FastAPI server utilizing **LangGraph** to manage complex AI states. It handles vector embeddings (FastEmbed), document retrieval (Qdrant), and LLM orchestration (Groq/Llama 3.3).
* **`/rag-frontend` (The Face)**: A modern Laravel 11 dashboard that manages user sessions, proxies API requests, and provides a rich UI using **Alpine.js** and **Tailwind CSS**.

---

## 🚀 Getting Started

### 📋 Prerequisites
Before you begin, ensure you have the following installed:
* **Python 3.10+** (for the Backend)
* **PHP 8.2+ & Composer** (for the Frontend)
* **Node.js & NPM** (for Frontend assets)
* **MySQL Server** (for relational data)
* **Qdrant Account/Instance** (for vector storage)

### 👯 1. Clone the Repository
```bash
git clone [https://github.com/Hammadwakeel/chatpdf.git](https://github.com/Hammadwakeel/chatpdf.git)
cd chatpdf
````

### ⚡ 2. Automated Setup (Recommended)

The fastest way to get started is using the master setup script:

```bash
bash setup_all.sh
```

*This script automates environment creation, dependency installation, and basic configuration for both modules.*

-----

## 🛠️ Manual Setup Guide

If you prefer to set up the components individually, follow these steps:

### 🐍 Part A: Backend (FastAPI)

1.  **Navigate & Environment**:
    ```bash
    cd backend
    python3 -m venv venv
    source venv/bin/activate
    ```
2.  **Install Dependencies**:
    ```bash
    pip install -r requirements.txt
    ```
3.  **Configure Environment**:
    Create a `.env` file in the `backend/` folder:
    ```env
    GROQ_API_KEY=your_key_here
    DATABASE_URL=mysql+pymysql://root:pass@127.0.0.1/chatpdf
    QDRANT_URL=your_qdrant_url
    QDRANT_API_KEY=your_qdrant_key
    SECRET_KEY=your_jwt_secret
    ```
4.  **Run Server**:
    ```bash
    uvicorn app.main:app --reload --port 8000
    ```

### 📦 Part B: Frontend (Laravel)

1.  **Navigate & Install**:
    ```bash
    cd ../rag-frontend
    composer install
    npm install && npm run build
    ```
2.  **Configure Environment**:
    Create a `.env` file in the `rag-frontend/` folder:
    ```env
    DB_CONNECTION=mysql
    DB_DATABASE=chatpdf
    FASTAPI_URL=[http://127.0.0.1:8000](http://127.0.0.1:8000)
    OCR_API_URL=[https://hammad712-urdu-ocr-app.hf.space/upload](https://hammad712-urdu-ocr-app.hf.space/upload)
    ```
3.  **Initialize Application**:
    ```bash
    php artisan key:generate
    php artisan migrate
    ```
4.  **Run Dashboard**:
    ```bash
    php artisan serve --port=8001
    ```

-----

## 🌟 Key Capabilities

  * **Agentic RAG**: Multi-node LangGraph workflow for high-accuracy retrieval.
  * **Multi-PDF Comparison**: Split-screen view for simultaneous document analysis.
  * **OCR Mode**: Ingest scanned images/PDFs into the RAG pipeline.
  * **Smart Suggestions**: AI-generated follow-up questions using Structured Output.

-----

## 📂 Project Navigation

  * **[Backend Documentation](https://www.google.com/search?q=./backend/README.md)**: Details on AI logic and endpoints.
  * **[Frontend Documentation](https://www.google.com/search?q=./rag-frontend/README.md)**: UI components and proxy logic.

-----

### 👨‍💻 Developer Profile

**Hammad Wakeel** *AI Engineer & Backend Developer*

----