/**
 * BC Assistant - skrypt główny
 * Poprawiona wersja z obsługą jQuery bez konfliktów
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
        displayMode: bcAssistantData.display_mode || "bubble", // Nowy parametr
        
        // Opcje debugowania
        debug: bcAssistantData.debug || false
    };

    // Klasa główna
    class BCAssistant {
        constructor(config) {
            this.config = config;
            this.messages = [];
            this.isOpen = false;
            this.isTyping = false;
            this.isMobileDevice = this.checkIfMobile();
            
            // Zapisz model w logach dla debugowania
            if (this.config.debug) {
                console.log("BC Assistant initialized with model:", this.config.model);
                console.log("Is mobile device:", this.isMobileDevice);
                console.log("Display mode:", this.config.displayMode);
            }
            
            this.init();
        }
        
        checkIfMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }
        
        init() {
            // Na urządzeniach mobilnych użyj trybu głosowego, jeśli został wybrany
            if (this.isMobileDevice && this.config.displayMode === 'voice') {
                this.initVoiceAssistant();
            } else {
                // Standardowa inicjalizacja czatu
                this.createElements();
                this.setupEventListeners();
                this.setupDraggable();
                this.addMessage('assistant', this.config.initialMessage);
            }
            
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
        
        setupDraggable() {
            const header = this.window.querySelector('.bc-assistant-header');
            
            if (!header) return;
            
            let isDragging = false;
            let offsetX, offsetY;
            
            header.addEventListener('mousedown', (e) => {
                isDragging = true;
                
                // Zapisz pozycję kliknięcia względem okna
                const rect = this.window.getBoundingClientRect();
                offsetX = e.clientX - rect.left;
                offsetY = e.clientY - rect.top;
                
                // Zmień kursor podczas przeciągania
                header.style.cursor = 'grabbing';
            });
            
            document.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                
                // Ustaw nową pozycję okna
                const x = e.clientX - offsetX;
                const y = e.clientY - offsetY;
                
                // Ogranicz pozycję do widocznego obszaru
                const maxX = window.innerWidth - this.window.offsetWidth;
                const maxY = window.innerHeight - this.window.offsetHeight;
                
                const boundedX = Math.max(0, Math.min(x, maxX));
                const boundedY = Math.max(0, Math.min(y, maxY));
                
                this.window.style.left = boundedX + 'px';
                this.window.style.top = boundedY + 'px';
                this.window.style.right = 'auto';
                this.window.style.bottom = 'auto';
            });
            
            document.addEventListener('mouseup', () => {
                isDragging = false;
                
                // Przywróć normalny kursor
                if (header) {
                    header.style.cursor = 'grab';
                }
            });
            
            // Ustaw początkowy kursor
            header.style.cursor = 'grab';
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
        
        // Implementacja trybu głosowego
        initVoiceAssistant() {
            console.log("Initializing voice assistant");
            
            // Utwórz minimalny UI - ikonę mikrofonu
            this.container = document.createElement('div');
            this.container.className = 'bc-assistant-voice-container';
            
            // Utwórz przycisk mikrofonu
            this.voiceButton = document.createElement('button');
            this.voiceButton.className = 'bc-assistant-voice-button';
            this.voiceButton.innerHTML = '<i class="fas fa-microphone"></i>';
            this.voiceButton.title = "Asystent głosowy";
            
            // Dodaj przycisk do kontenera
            this.container.appendChild(this.voiceButton);
            
            // Dodaj kontener do strony
            document.body.appendChild(this.container);
            
            // Dodaj obsługę kliknięcia
            this.voiceButton.addEventListener('click', () => {
                this.startVoiceRecognition();
            });
        }
        
        // Rozpocznij rozpoznawanie mowy
        startVoiceRecognition() {
            // Sprawdź czy przeglądarka obsługuje rozpoznawanie mowy
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                alert("Przepraszamy, Twoja przeglądarka nie obsługuje rozpoznawania mowy.");
                return;
            }
            
            // Zmień ikonę na aktywną
            this.voiceButton.innerHTML = '<i class="fas fa-microphone-alt"></i>';
            this.voiceButton.classList.add('recording');
            
            // Utwórz instancję rozpoznawania mowy
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const recognition = new SpeechRecognition();
            
            recognition.lang = 'pl-PL';
            recognition.continuous = false;
            recognition.interimResults = false;
            
            recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                console.log("Voice recognized:", transcript);
                
                // Przetwórz rozpoznany tekst
                this.processVoiceInput(transcript);
            };
            
            recognition.onerror = (event) => {
                console.error("Speech recognition error:", event.error);
                // Przywróć ikonę mikrofonu
                this.voiceButton.innerHTML = '<i class="fas fa-microphone"></i>';
                this.voiceButton.classList.remove('recording');
            };
            
            recognition.onend = () => {
                // Przywróć ikonę mikrofonu
                this.voiceButton.innerHTML = '<i class="fas fa-microphone"></i>';
                this.voiceButton.classList.remove('recording');
            };
            
            // Rozpocznij rozpoznawanie
            recognition.start();
        }
        
        // Przetwórz dane wejściowe głosowe
        processVoiceInput(text) {
            console.log("Processing voice input:", text);
            
            // Pokaż tekst użytkownika
            this.showSpeechBubble('user', text);
            
            // Wskaźnik ładowania
            this.showSpeechBubble('loading', '');
            
            // Wyślij zapytanie do API
            this.sendRequest(text)
                .then(response => {
                    // Ukryj wskaźnik ładowania
                    this.hideSpeechBubble('loading');
                    
                    // Pokaż odpowiedź asystenta
                    this.showSpeechBubble('assistant', response.message);
                    
                    // Odczytaj odpowiedź
                    this.speakResponse(response.message);
                    
                    // Sprawdź czy pytanie dotyczy kontaktu z recepcją
                    if (text.toLowerCase().includes('recepcj') || text.toLowerCase().includes('umówi') || 
                        text.toLowerCase().includes('wizyt') || text.toLowerCase().includes('kontakt')) {
                        // Pokaż przycisk połączenia z recepcją
                        this.showCallButton();
                    }
                })
                .catch(error => {
                    // Ukryj wskaźnik ładowania
                    this.hideSpeechBubble('loading');
                    
                    // Pokaż błąd
                    this.showSpeechBubble('assistant', 'Przepraszam, wystąpił błąd. Spróbuj ponownie.');
                    console.error('BC Assistant Error:', error);
                });
        }
        
        // Pokaż bąbelek mowy
        showSpeechBubble(role, content) {
            // Usuń poprzedni bąbelek tego samego typu
            const existingBubble = document.querySelector(`.bc-assistant-speech-bubble.${role}`);
            if (existingBubble) {
                existingBubble.remove();
            }
            
            // Utwórz nowy bąbelek
            const bubble = document.createElement('div');
            bubble.className = `bc-assistant-speech-bubble ${role}`;
            
            if (role === 'loading') {
                bubble.innerHTML = '<div class="bc-assistant-loading-dots"><span></span><span></span><span></span></div>';
            } else {
                bubble.textContent = content;
            }
            
            // Dodaj bąbelek do strony
            document.body.appendChild(bubble);
            
            // Automatycznie ukryj bąbelek po czasie (poza loading)
            if (role !== 'loading') {
                setTimeout(() => {
                    bubble.classList.add('fade-out');
                    setTimeout(() => {
                        bubble.remove();
                    }, 500);
                }, 5000);
            }
        }
        
        // Ukryj bąbelek mowy
        hideSpeechBubble(role) {
            const bubble = document.querySelector(`.bc-assistant-speech-bubble.${role}`);
            if (bubble) {
                bubble.classList.add('fade-out');
                setTimeout(() => {
                    bubble.remove();
                }, 500);
            }
        }
        
        // Odczytaj odpowiedź za pomocą syntezy mowy
        speakResponse(text) {
            // Sprawdź czy przeglądarka obsługuje syntezę mowy
            if (!('speechSynthesis' in window)) {
                console.error("Speech synthesis not supported");
                return;
            }
            
            // Zatrzymaj poprzednią mowę
            window.speechSynthesis.cancel();
            
            // Utwórz nową wypowiedź
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'pl-PL';
            utterance.rate = 1.0;
            utterance.pitch = 1.0;
            
            // Znajdź polski głos
            const voices = window.speechSynthesis.getVoices();
            const polishVoice = voices.find(voice => voice.lang.includes('pl'));
            if (polishVoice) {
                utterance.voice = polishVoice;
            }
            
            // Odtwórz
            window.speechSynthesis.speak(utterance);
        }
        
        // Pokaż przycisk połączenia z recepcją
        showCallButton() {
            // Usuń poprzedni przycisk, jeśli istnieje
            const existingButton = document.querySelector('.bc-assistant-call-button');
            if (existingButton) {
                existingButton.remove();
            }
            
            // Utwórz przycisk
            const callButton = document.createElement('button');
            callButton.className = 'bc-assistant-call-button';
            callButton.innerHTML = '<i class="fas fa-phone"></i> Połącz z recepcją';
            
            // Dodaj obsługę kliknięcia
            callButton.addEventListener('click', () => {
                window.location.href = 'tel:+48123456789';
            });
            
            // Dodaj przycisk do strony
            document.body.appendChild(callButton);
            
            // Automatycznie ukryj przycisk po 10 sekundach
            setTimeout(() => {
                callButton.classList.add('fade-out');
                setTimeout(() => {
                    callButton.remove();
                }, 500);
            }, 10000);
        }
    }

    // Inicjalizacja asystenta po załadowaniu strony
    $(document).ready(function() {
        // Utwórz instancję asystenta
        try {
            window.bcAssistant = new BCAssistant(bcAssistantConfig);
            if (bcAssistantConfig.debug) {
                console.log("BC Assistant successfully initialized");
            }
        } catch (error) {
            console.error("BC Assistant initialization error:", error);
        }
    });
    
})(jQuery); // Use the main jQuery instance instead of creating a new one