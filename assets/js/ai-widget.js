class AIAssistant {
    constructor(role) {
        this.role = role;
        this.isOpen = false;
        this.messages = [];
        this.init();
    }

    init() {
        this.createWidget();
        this.loadMessages();
        this.bindEvents();
    }

    createWidget() {
        const widgetHTML = `
            <div class="ai-widget ${this.role}">
                <div class="ai-widget-button" id="aiWidgetBtn">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="ai-chat-window" id="aiChatWindow">
                    <div class="ai-chat-header">
                        <h4><i class="fas fa-robot"></i> AI Assistant (${this.role})</h4>
                        <span class="close-btn" id="closeChat">&times;</span>
                    </div>
                    <div class="ai-chat-messages" id="chatMessages"></div>
                    <div class="ai-chat-input">
                        <input type="text" id="chatInput" placeholder="Ask me anything..." />
                        <button id="sendMessage"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', widgetHTML);
    }

    bindEvents() {
        document.getElementById('aiWidgetBtn').addEventListener('click', () => this.toggleChat());
        document.getElementById('closeChat').addEventListener('click', () => this.closeChat());
        document.getElementById('sendMessage').addEventListener('click', () => this.sendMessage());
        document.getElementById('chatInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.sendMessage();
        });
    }

    toggleChat() {
        const chatWindow = document.getElementById('aiChatWindow');
        const btn = document.getElementById('aiWidgetBtn');
        if (this.isOpen) {
            chatWindow.style.display = 'none';
            btn.classList.remove('active');
        } else {
            chatWindow.style.display = 'flex';
            btn.classList.add('active');
            this.displayMessages();
        }
        this.isOpen = !this.isOpen;
    }

    closeChat() {
        document.getElementById('aiChatWindow').style.display = 'none';
        document.getElementById('aiWidgetBtn').classList.remove('active');
        this.isOpen = false;
    }

    loadMessages() {
        const saved = localStorage.getItem(`aiChat_${this.role}`);
        if (saved) {
            this.messages = JSON.parse(saved);
        } else {
            // Welcome message
            const welcome = this.getWelcomeMessage();
            this.messages.push({ sender: 'bot', text: welcome, time: new Date() });
        }
    }

    getWelcomeMessage() {
        switch(this.role) {
            case 'admin':
                return "👋 Hello Admin! I can help with system stats. Try asking about students, teachers, courses, or revenue.";
            case 'teacher':
                return "👋 Hi Teacher! I'm here to help. Ask me about your courses, students, pending grades, or exams.";
            case 'student':
                return "👋 Hello Student! I'm your learning assistant. Ask about your courses, progress, certificates, or exams.";
            default:
                return "👋 Hi! How can I help you today?";
        }
    }

    displayMessages() {
        const container = document.getElementById('chatMessages');
        container.innerHTML = '';
        this.messages.forEach(msg => {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${msg.sender}`;

            const bubble = document.createElement('div');
            bubble.className = 'message-bubble';
            bubble.textContent = msg.text;

            const time = document.createElement('div');
            time.className = 'message-time';
            time.textContent = this.formatTime(new Date(msg.time));

            messageDiv.appendChild(bubble);
            messageDiv.appendChild(time);
            container.appendChild(messageDiv);
        });
        container.scrollTop = container.scrollHeight;
    }

    formatTime(date) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    async sendMessage() {
        const input = document.getElementById('chatInput');
        const text = input.value.trim();
        if (!text) return;

        // Add user message
        this.messages.push({ sender: 'user', text: text, time: new Date() });
        this.displayMessages();
        input.value = '';

        // Show typing indicator
        this.showTyping();

        try {
            const formData = new FormData();
            formData.append('message', text);
            formData.append('context', this.role);

            const response = await fetch('../includes/ai-assistant.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            this.hideTyping();

            if (data.error) {
                this.messages.push({ sender: 'bot', text: 'Sorry, I encountered an error. Please try again.', time: new Date() });
            } else {
                this.messages.push({ sender: 'bot', text: data.response, time: new Date() });
            }
            this.displayMessages();
            this.saveMessages();

        } catch (error) {
            this.hideTyping();
            this.messages.push({ sender: 'bot', text: 'Network error. Please check your connection.', time: new Date() });
            this.displayMessages();
        }
    }

    showTyping() {
        const container = document.getElementById('chatMessages');
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message bot';
        typingDiv.id = 'typingIndicator';
        typingDiv.innerHTML = '<div class="typing-indicator"><span></span><span></span><span></span></div>';
        container.appendChild(typingDiv);
        container.scrollTop = container.scrollHeight;
    }

    hideTyping() {
        const typing = document.getElementById('typingIndicator');
        if (typing) typing.remove();
    }

    saveMessages() {
        // Keep only last 50 messages
        if (this.messages.length > 50) {
            this.messages = this.messages.slice(-50);
        }
        localStorage.setItem(`aiChat_${this.role}`, JSON.stringify(this.messages));
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Read role from meta tag or body class
    const roleMeta = document.querySelector('meta[name="user-role"]');
    const role = roleMeta ? roleMeta.content : 'student';
    new AIAssistant(role);
});