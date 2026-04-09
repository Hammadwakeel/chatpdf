@extends('layouts.app')
@section('content')
<div class="flex h-screen overflow-hidden text-gray-800" x-data="chatApp()">
    
    <div x-show="toast.show" x-transition x-cloak :class="toast.type === 'success' ? 'bg-emerald-500' : 'bg-red-500'"
         class="fixed top-5 right-5 z-[200] text-white px-6 py-3 rounded-2xl shadow-2xl flex items-center gap-3 font-medium">
        <span x-text="toast.message"></span>
        <button @click="toast.show = false">&times;</button>
    </div>

    <aside class="w-80 bg-white border-r border-gray-200 flex flex-col shadow-sm">
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

    <main class="flex-grow flex flex-col bg-white">
        <header class="h-16 border-b border-gray-100 flex items-center px-8 justify-between">
            <div class="flex items-center gap-3">
                <span class="px-2 py-1 bg-indigo-50 text-indigo-600 text-[10px] font-bold rounded uppercase tracking-wider" x-text="viewMode"></span>
                <h2 class="font-semibold text-gray-700" x-text="activeTitle || 'Select a document'"></h2>
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

        <div class="p-8">
            <div class="max-w-4xl mx-auto flex items-center gap-4 bg-white border border-gray-200 rounded-2xl p-2 focus-within:ring-2 ring-indigo-100 transition shadow-sm">
                <input type="text" x-model="userInput" @keydown.enter="send()" :disabled="isSending" class="flex-grow bg-transparent border-none focus:ring-0 px-4 text-sm" placeholder="Ask a question...">
                <button @click="send()" :disabled="isSending || !activeSession" class="bg-indigo-600 hover:bg-indigo-700 p-2.5 rounded-xl text-white transition">
                    <svg x-show="!isSending" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M14 5l7 7m0 0l-7 7m7-7H3" stroke-width="2" stroke-linecap="round"/></svg>
                    <svg x-show="isSending" class="w-5 h-5 animate-spin" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </button>
            </div>
        </div>
    </main>

    <div x-show="showModal" x-cloak class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm flex items-center justify-center z-[110] p-6">
        <div class="bg-white rounded-3xl w-full max-w-md p-8 shadow-2xl">
            <h3 class="text-xl font-bold" x-text="viewMode === 'ocr' ? 'OCR & Chat' : 'Upload Document'"></h3>
            <p class="text-xs text-gray-500 mt-1 mb-6" x-text="viewMode === 'ocr' ? 'Select an Urdu PDF or Image (Max 5 pages)' : 'Select PDFs for analysis'"></p>
            
            <div class="space-y-2 mb-6">
                <template x-for="(file, index) in stagedFiles" :key="index">
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100 text-xs">
                        <span class="truncate" x-text="file.name"></span>
                        <button @click="stagedFiles.splice(index, 1)" class="text-red-400 font-bold">&times;</button>
                    </div>
                </template>
            </div>

            <label x-show="(viewMode !== 'compare' && stagedFiles.length < 1) || (viewMode === 'compare' && stagedFiles.length < 5)" 
                   class="w-full flex items-center justify-center gap-2 py-3 border-2 border-dashed border-gray-200 rounded-xl cursor-pointer hover:border-indigo-400 hover:bg-indigo-50 transition">
                <input type="file" @change="stagedFiles.push($event.target.files[0])" class="hidden" :accept="viewMode === 'ocr' ? '.pdf,.jpg,.jpeg,.png' : '.pdf'">
                <span class="text-sm font-bold text-indigo-600">+ Add File</span>
            </label>

            <button @click="upload()" :disabled="isUploading || stagedFiles.length === 0" class="w-full mt-4 py-3 bg-indigo-600 text-white font-bold rounded-xl shadow-lg hover:bg-indigo-700 disabled:opacity-30 flex items-center justify-center gap-2">
                <span x-show="isUploading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
                <span x-text="isUploading ? 'OCR-ing & Analyzing...' : 'Start Session'"></span>
            </button>
        </div>
    </div>
</div>

<script>
function chatApp() {
    return {
        viewMode: 'qna',
        showModal: false,
        isUploading: false,
        isSending: false,
        activeSession: null,
        activeTitle: '',
        userInput: '',
        messages: [],
        stagedFiles: [],
        toast: { show: false, message: '', type: 'success' },

        renderMarkdown(content) { return marked.parse(content || '', { breaks: true }); },
        setMode(mode) { this.viewMode = mode; this.stagedFiles = []; this.activeSession = null; this.messages = []; },
        notify(msg, type = 'success') { this.toast = { show: true, message: msg, type }; setTimeout(() => this.toast.show = false, 4000); },

        async loadSession(id, title) {
            this.activeSession = id; this.activeTitle = title;
            const res = await axios.get(`/chat/${id}/messages`);
            this.messages = res.data.map(m => ({ id: Math.random(), role: m.role, content: m.content }));
            this.scroll();
        },

        async upload() {
            this.isUploading = true;
            const fd = new FormData();
            this.stagedFiles.forEach(f => fd.append('files[]', f));
            
            const endpoint = this.viewMode === 'ocr' ? '/ocr-upload' : '/upload';
            
            try {
                await axios.post(endpoint, fd);
                this.notify('Document ready for chat!');
                setTimeout(() => location.reload(), 1000);
            } catch (e) {
                this.notify(e.response?.data?.detail || 'Processing Error', 'error');
            } finally { this.isUploading = false; }
        },

        async send() {
            if(!this.userInput || !this.activeSession) return;
            const text = this.userInput;
            this.messages.push({ id: Date.now(), role: 'human', content: text });
            this.userInput = ''; this.isSending = true; this.scroll();
            const res = await axios.post('/ask', { session_id: this.activeSession, question: text });
            this.messages.push({ id: Date.now()+1, role: 'ai', content: res.data.answer });
            this.isSending = false; this.scroll();
        },

        scroll() { setTimeout(() => { const c = document.getElementById('chat-container'); c.scrollTop = c.scrollHeight; }, 100); }
    }
}
</script>
@endsection
