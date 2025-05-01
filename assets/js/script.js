/**
 * BC Assistant - skrypt główny
 * Poprawiona wersja z dynamicznym wyborem modelu
 */

(function($) {
    "use strict";
    
    // Globalne dane konfiguracyjne BC Assistant
    const bcAssistantData = window.bcAssistantData || { 
        model: "gpt-4o",
        apiEndpoint: "/wp-admin/admin-ajax.php",
        action: "bc_assistant_send_message"
    };

    // Konfiguracja asystenta
    const bcAssistantConfig = {
        // Użyj dynamicznego modelu z ustawień WordPress zamiast stałej wartości
        model: bcAssistantData.model,
        
        // Pozostałe parametry
        position: bcAssistantData.position || "bottom-right",
        avatar: bcAssistantData.avatar || "",
        title: bcAssistantData.title || "BC Assistant",
        initialMessage: bcAssistantData.initialMessage || "Witaj! W czym mogę pomóc?",
        
        // Opcje debugowania
        debug: false
    };

    // Klasa główna
    class BCAssistant {
        constructor(config) {
            this.config = config;
            this.messages = [];
            this.isOpen = false;
            this.isTyping = false;
            
            // Zapisz model w logach dla debugowania
            if (this.config.debug) {
                console.log("BC Assistant initialized with model:", this.config.model);
            }
            
            this.init();
        }
        
        init() {
            // Inicjalizacja interfejsu
            this.createElements();
            this.setupEventListeners();
            
            // Dodaj początkową wiadomość asystenta
            this.addMessage('assistant', this.config.initialMessage);
            
            // Zaloguj inicjalizację
            if (this.config.debug) {
                console.log("BC Assistant initialized");
            }
        }
        
        createElements() {
            // Tworzenie głównego kontenera
            this.container = document.createElement('div');
            this.container.className = 'bc-assistant-container';
            
            // Ustawienie pozycji na podstawie konfiguracji
            this.container.classList.add(`bc-position-${this.config.position}`);
            
            // Utwórz przycisk bąbelkowy
            this.bubble = document.createElement('div');
            this.bubble.className = 'bc-assistant-bubble';
            
            // Utwórz avatar (jeśli podano)
            if (this.config.avatar) {
                const avatarImg = document.createElement('img');
                avatarImg.src = this.config.avatar;
                avatarImg.alt = 'Assistant Avatar';
                avatarImg.className = 'bc-avatar';
                this.bubble.appendChild(avatarImg);
            } else {
                // Domyślna ikona
                this.bubble.innerHTML = '<div class="bc-default-avatar"></div>';
            }
            
            // Utwórz okno asystenta
            this.window = document.createElement('div');
            this.window.className = 'bc-assistant-window';
            this.window.style.display = 'none';
            
            // Nagłówek
            const header = document.createElement('div');
            header.className = 'bc-assistant-header';
            
            const title = document.createElement('div');
            title.className = 'bc-assistant-title';
            title.textContent = this.config.title;
            
            const closeBtn = document.createElement('button');
            closeBtn.className = 'bc-assistant-close';
            closeBtn.innerHTML = '&times;';
            closeBtn.onclick = () => this.toggleWindow();
            
            header.appendChild(title);
            header.appendChild(closeBtn);
            
            // Kontener wiadomości
            this.messagesContainer = document.createElement('div');
            this.messagesContainer.className = 'bc-assistant-messages';
            
            // Pole wiadomości
            const inputContainer = document.createElement('div');
            inputContainer.className = 'bc-assistant-input-container';
            
            this.inputField = document.createElement('textarea');
            this.inputField.className = 'bc-assistant-input';
            this.inputField.placeholder = 'Wpisz wiadomość...';
            
            const sendButton = document.createElement('button');
            sendButton.className = 'bc-assistant-send';
            sendButton.textContent = 'Wyślij';
            sendButton.onclick = () => this.sendMessage();
            
            inputContainer.appendChild(this.inputField);
            inputContainer.appendChild(sendButton);
            
            // Złożenie okna
            this.window.appendChild(header);
            this.window.appendChild(this.messagesContainer);
            this.window.appendChild(inputContainer);
            
            // Dodaj elementy do kontenera
            this.container.appendChild(this.bubble);
            this.container.appendChild(this.window);
            
            // Dodaj kontener do strony
            document.body.appendChild(this.container);
        }
        
        setupEventListeners() {
            // Kliknięcie bąbelka
            this.bubble.addEventListener('click', () => {
                this.toggleWindow();
            });
            
            // Obsługa klawisza Enter w polu wiadomości
            this.inputField.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
        }
        
        toggleWindow() {
            this.isOpen = !this.isOpen;
            this.window.style.display = this.isOpen ? 'flex' : 'none';
            
            if (this.isOpen) {
                this.inputField.focus();
                this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
            }
        }
        
        addMessage(role, content) {
            const message = {
                role,
                content,
                timestamp: new Date()
            };
            
            this.messages.push(message);
            
            // Dodaj wiadomość do interfejsu
            const messageElement = document.createElement('div');
            messageElement.className = `bc-message bc-message-${role}`;
            
            const messageContent = document.createElement('div');
            messageContent.className = 'bc-message-content';
            messageContent.innerHTML = this.formatMessage(content);
            
            messageElement.appendChild(messageContent);
            this.messagesContainer.appendChild(messageElement);
            
            // Przewiń do najnowszej wiadomości
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
        }
        
        formatMessage(text) {
            // Podstawowe formatowanie tekstu (można rozszerzyć o Markdown)
            return text
                .replace(/\n/g, '<br>')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>');
        }
        
        sendMessage() {
            const message = this.inputField.value.trim();
            
            if (!message || this.isTyping) {
                return;
            }
            
            // Dodaj wiadomość użytkownika do interfejsu
            this.addMessage('user', message);
            
            // Wyczyść pole wiadomości
            this.inputField.value = '';
            
            // Pokaż, że asystent pisze
            this.isTyping = true;
            this.showTypingIndicator();
            
            // Wyślij zapytanie do API
            this.sendRequest(message)
                .then(response => {
                    // Ukryj wskaźnik pisania
                    this.hideTypingIndicator();
                    
                    // Dodaj odpowiedź asystenta do interfejsu
                    this.addMessage('assistant', response.message);
                    
                    this.isTyping = false;
                })
                .catch(error => {
                    this.hideTypingIndicator();
                    this.addMessage('assistant', 'Przepraszam, wystąpił błąd. Spróbuj ponownie.');
                    this.isTyping = false;
                    
                    if (this.config.debug) {
                        console.error('BC Assistant Error:', error);
                    }
                });
        }
        
        showTypingIndicator() {
            const indicatorElement = document.createElement('div');
            indicatorElement.className = 'bc-message bc-message-assistant bc-typing-indicator';
            indicatorElement.innerHTML = '<div class="bc-message-content"><div class="bc-typing-dots"><span></span><span></span><span></span></div></div>';
            
            this.messagesContainer.appendChild(indicatorElement);
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
        }
        
        hideTypingIndicator() {
            const indicator = this.messagesContainer.querySelector('.bc-typing-indicator');
            if (indicator) {
                indicator.remove();
            }
        }
        
        async sendRequest(message) {
            // Przygotuj dane zapytania
            const formData = new FormData();
            formData.append('action', bcAssistantData.action);
            formData.append('message', message);
            formData.append('model', this.config.model);
            
            // Dodaj nonce dla bezpieczeństwa
            if (bcAssistantData.nonce) {
                formData.append('nonce', bcAssistantData.nonce);
            }
            
            // Wyślij zapytanie AJAX do WordPress
            const response = await fetch(bcAssistantData.apiEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Unknown error');
            }
            
            return data.data;
        }
    }

    // Inicjalizacja asystenta po załadowaniu strony
    $(document).ready(function() {
        // Utwórz instancję asystenta
        window.bcAssistant = new BCAssistant(bcAssistantConfig);
    });
    
})(jQuery);