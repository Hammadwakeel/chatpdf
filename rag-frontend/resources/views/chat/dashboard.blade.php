@extends('layouts.app')
@section('content')
<div class="flex h-screen overflow-hidden text-gray-800" x-data="chatApp()">
    
    <div x-show="toast.show" x-transition x-cloak :class="toast.type === 'success' ? 'bg-emerald-500' : 'bg-red-500'"
         class="fixed top-5 right-5 z-[200] text-white px-6 py-3 rounded-2xl shadow-2xl flex items-center gap-3 font-medium">
        <span x-text="toast.message"></span>
        <button @click="toast.show = false">&times;</button>
    </div>

    <aside class="w-80 bg-white border-r border-gray-200 flex flex-col shadow-sm z-10">
        <div class="p-6 border-b border-gray-100 flex items-center gap-2">
            <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold shadow-lg shadow-indigo-100">Q</div>
            <h1 class="font-bold text-gray-800 tracking-tight">ChatPDF</h1>
        </div>

        <div class="p-4 space-y-2">
            <div class="flex p-1 bg-gray-100 rounded-xl">
                <button @click="setMode('qna')" :class="viewMode === 'qna' ? 'bg-white shadow-sm text-indigo-600' : 'text-gray-500'" class="flex-1 py-1.5 text-[10px] font-bold rounded-lg transition-all">Q&A</button>
                <button @click="setMode('compare')" :class="viewMode === 'compare' ? 'bg-white shadow-sm text-indigo-600' : 'text-gray-500'" class="flex-1 py-1.5 text-[10px] font-bold rounded-lg transition-all">COMPARE</button>
                <button @click="setMode('ocr')" :class="viewMode === 'ocr' ? 'bg-white shadow-sm text-indigo-600' : 'text-gray-500'" class="flex-1 py-1.5 text-[10px] font-bold rounded-lg transition-all">OCR</button>
            </div>
            
            <button @click="showModal = true" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-4 rounded-xl flex items-center justify-center gap-2 transition-all mt-4">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round"/></svg>
                <span x-text="viewMode === 'ocr' ? 'OCR PDF/Image' : 'New Upload'"></span>
            </button>
        </div>

        <div class="flex-grow overflow-y-auto px-4 pb-4 space-y-1">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-2 mb-2" x-text="viewMode.toUpperCase() + ' HISTORY'"></p>
            @foreach($history as $chat)
                <button @click="loadSession('{{ $chat['session_id'] }}', '{{ $chat['filename'] }}')" 
                    class="w-full text-left p-3 rounded-xl hover:bg-gray-50 text-sm font-medium text-gray-600 truncate transition border border-transparent"
                    :class="activeSession === '{{ $chat['session_id'] }}' ? 'bg-indigo-50 border-indigo-100 text-indigo-700' : ''">
                    {{ $chat['filename'] }}
                </button>
            @endforeach
        </div>
    </aside>

    <main class="flex-grow flex flex-col bg-white relative">
        <header class="h-16 border-b border-gray-100 flex items-center px-8 justify-between bg-white z-10">
            <div class="flex items-center gap-3">
                <span class="px-2 py-1 bg-indigo-50 text-indigo-600 text-[10px] font-bold rounded uppercase tracking-wider" x-text="viewMode"></span>
                <h2 class="font-semibold text-gray-700" x-text="activeTitle || 'Select a document'"></h2>
                
                <button x-show="activeSession && viewMode !== 'ocr'" @click="viewPdf()" 
                        class="ml-4 text-[10px] bg-gray-100 text-gray-600 px-3 py-1.5 rounded-lg font-bold hover:bg-indigo-50 hover:text-indigo-600 transition flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    VIEW PDF
                </button>
            </div>
            <a href="/logout" class="text-xs text-gray-400 hover:text-red-500">Sign Out</a>
        </header>

        <div class="flex-grow overflow-y-auto p-12 space-y-8 bg-[#FBFBFF]" id="chat-container">
            <template x-for="msg in messages" :key="msg.id">
                <div :class="msg.role === 'human' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="msg.role === 'human' ? 'bg-indigo-600 text-white shadow-md' : 'bg-white text-gray-800 border border-gray-200 shadow-sm'" 
                         class="max-w-3xl px-6 py-4 rounded-2xl text-sm leading-relaxed">
                        <template x-if="msg.role === 'ai'">
                            <div class="prose prose-sm prose-indigo max-w-none" x-html="renderMarkdown(msg.content)"></div>
                        </template>
                        <template x-if="msg.role === 'human'">
                            <p x-text="msg.content"></p>
                        </template>
                    </div>
                </div>
            </template>
            <div x-show="isSending" class="flex justify-start"><div class="bg-gray-100 px-6 py-4 rounded-2xl flex items-center gap-1"><div class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce"></div><div class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce delay-75"></div><div class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce delay-150"></div></div></div>
        </div>

        <div class="p-8 bg-white border-t border-gray-50">
            <div x-show="suggestions.length > 0" class="max-w-4xl mx-auto mb-3 flex flex-wrap gap-2">
                <template x-for="q in suggestions" :key="q">
                    <button @click="askSuggested(q)" 
                            class="text-[11px] bg-white border border-indigo-100 text-indigo-600 px-4 py-2 rounded-xl hover:bg-indigo-50 transition shadow-sm font-medium text-left">
                        ✨ <span x-text="q"></span>
                    </button>
                </template>
            </div>

            <div class="max-w-4xl mx-auto flex items-center gap-4 bg-white border border-gray-200 rounded-2xl p-2 focus-within:ring-2 ring-indigo-100 transition shadow-sm">
                <input type="text" x-model="userInput" @keydown.enter="send()" :disabled="isSending" class="flex-grow bg-transparent border-none focus:ring-0 px-4 text-sm" placeholder="Ask a question about the document...">
                <button @click="send()" :disabled="isSending || !activeSession" class="bg-indigo-600 hover:bg-indigo-700 p-2.5 rounded-xl text-white transition disabled:opacity-30">
                    <svg x-show="!isSending" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M14 5l7 7m0 0l-7 7m7-7H3" stroke-width="2" stroke-linecap="round"/></svg>
                    <svg x-show="isSending" class="w-5 h-5 animate-spin" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </button>
            </div>
        </div>
    </main>

    <div x-show="showPdfModal" x-cloak class="fixed inset-0 bg-gray-900/95 backdrop-blur-sm z-[150] p-6 flex flex-col transition-opacity">
        <div class="flex justify-between items-center mb-4 text-white">
            <h3 class="font-bold text-lg flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <span x-text="'Viewing: ' + activeTitle"></span>
            </h3>
            <button @click="showPdfModal = false" class="text-3xl hover:text-red-400 transition">&times;</button>
        </div>
        
        <div class="flex-grow flex gap-4 overflow-hidden">
            <template x-for="url in pdfUrls" :key="url">
                <div class="flex-1 bg-white rounded-2xl overflow-hidden shadow-2xl relative">
                    <div class="absolute inset-0 flex items-center justify-center bg-gray-50 -z-10">
                        <svg class="animate-spin h-8 w-8 text-indigo-400" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </div>
                    <iframe :src="url" class="w-full h-full border-none relative z-10"></iframe>
                </div>
            </template>
        </div>
    </div>

    <div x-show="showUploadModal" x-cloak class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm flex items-center justify-center z-[110] p-6">
        <div class="bg-white rounded-3xl w-full max-w-md p-8 shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-xl font-bold" x-text="viewMode === 'ocr' ? 'OCR & Chat' : 'Upload Document'"></h3>
                    <p class="text-xs text-gray-500 mt-1" x-text="viewMode === 'ocr' ? 'Select an Urdu PDF or Image' : 'Select PDFs for analysis'"></p>
                </div>
                <button @click="showUploadModal = false" class="text-gray-400 hover:text-red-500 text-2xl">&times;</button>
            </div>
            
            <div class="space-y-2 mb-6">
                <template x-for="(file, index) in stagedFiles" :key="index">
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs">
                        <span class="truncate font-medium text-gray-600" x-text="file.name"></span>
                        <button @click="stagedFiles.splice(index, 1)" class="text-red-400 hover:text-red-600 font-bold">&times;</button>
                    </div>
                </template>
            </div>

            <label x-show="(viewMode !== 'compare' && stagedFiles.length < 1) || (viewMode === 'compare' && stagedFiles.length < 5)" 
                   class="w-full flex items-center justify-center gap-2 py-4 border-2 border-dashed border-indigo-200 rounded-xl cursor-pointer hover:border-indigo-400 hover:bg-indigo-50 transition bg-indigo-50/50">
                <input type="file" @change="stagedFiles.push($event.target.files[0])" class="hidden" :accept="viewMode === 'ocr' ? '.pdf,.jpg,.jpeg,.png' : '.pdf'">
                <span class="text-sm font-bold text-indigo-600">+ Browse Files</span>
            </label>

            <button @click="upload()" :disabled="isUploading || stagedFiles.length === 0" class="w-full mt-6 py-3 bg-indigo-600 text-white font-bold rounded-xl shadow-lg hover:bg-indigo-700 disabled:opacity-30 flex items-center justify-center gap-2 transition active:scale-95">
                <span x-show="isUploading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
                <span x-text="isUploading ? 'Analyzing & Indexing...' : 'Start Session'"></span>
            </button>
        </div>
    </div>
</div>

<script>
function chatApp() {
    return {
        viewMode: 'qna',
        showUploadModal: false,
        showPdfModal: false,
        isUploading: false,
        isSending: false,
        activeSession: null,
        activeTitle: '',
        userInput: '',
        messages: [],
        stagedFiles: [],
        suggestions: [],
        pdfUrls: [],
        toast: { show: false, message: '', type: 'success' },
        fastApiUrl: "{{ env('FASTAPI_URL', 'http://127.0.0.1:8000') }}",

        get showModal() { return this.showUploadModal; },
        set showModal(val) { this.showUploadModal = val; },

        renderMarkdown(content) { return marked.parse(content || '', { breaks: true, gfm: true }); },
        setMode(mode) { this.viewMode = mode; this.stagedFiles = []; this.activeSession = null; this.messages = []; this.suggestions = []; this.activeTitle = ''; },
        notify(msg, type = 'success') { this.toast = { show: true, message: msg, type }; setTimeout(() => this.toast.show = false, 4000); },

        async loadSession(id, title) {
            this.activeSession = id; 
            this.activeTitle = title;
            this.suggestions = []; // Reset on load
            
            try {
                // Fetch Chat History
                const res = await axios.get(`/chat/${id}/messages`);
                this.messages = res.data.map(m => ({ id: Math.random(), role: m.role, content: m.content }));
                this.scroll();

                // Fetch Suggested Questions
                const suggestRes = await axios.post('/suggested-questions', { session_id: id });
                if(suggestRes.data.questions) {
                    this.suggestions = suggestRes.data.questions;
                }
            } catch (e) {
                this.notify('Failed to load session data.', 'error');
            }
        },

        viewPdf() {
            // Clean up the title (e.g., "Comparison: a.pdf, b.pdf" -> ["a.pdf", "b.pdf"])
            // We strip '...' as well to handle older sessions created before the backend fix
            let filenamesStr = this.activeTitle.replace('Comparison: ', '').replace('...', '');
            let filenames = filenamesStr.split(',').map(name => name.trim());
            
            // Map to FastAPI static files route
            this.pdfUrls = filenames.map(name => `${this.fastApiUrl}/pdfs/${name}`);
            this.showPdfModal = true;
        },

        askSuggested(question) {
            this.userInput = question;
            this.suggestions = this.suggestions.filter(q => q !== question); // Remove the asked question
            this.send();
        },

        async upload() {
            this.isUploading = true;
            const fd = new FormData();
            this.stagedFiles.forEach(f => fd.append('files[]', f));
            const endpoint = this.viewMode === 'ocr' ? '/ocr-upload' : '/upload';
            
            try {
                await axios.post(endpoint, fd);
                this.notify('Document processed and questions generated!');
                setTimeout(() => location.reload(), 1500);
            } catch (e) {
                this.notify(e.response?.data?.detail || 'Processing Error', 'error');
            } finally { this.isUploading = false; }
        },

        async send() {
            if(!this.userInput || !this.activeSession) return;
            const text = this.userInput;
            this.messages.push({ id: Date.now(), role: 'human', content: text });
            this.userInput = ''; 
            this.isSending = true; 
            this.scroll();

            try {
                const res = await axios.post('/ask', { session_id: this.activeSession, question: text });
                this.messages.push({ id: Date.now()+1, role: 'ai', content: res.data.answer });
            } catch (e) {
                this.notify('AI failed to respond. Please retry.', 'error');
            } finally {
                this.isSending = false; 
                this.scroll();
            }
        },

        scroll() { setTimeout(() => { const c = document.getElementById('chat-container'); c.scrollTop = c.scrollHeight; }, 100); }
    }
}
</script>
@endsection
