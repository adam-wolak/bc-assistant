/**
 * BC Assistant - główny skrypt
 */
 
(function($) {
    'use strict';
    
    // Główna klasa asystenta
    class BCAssistant {
        constructor(element) {
            // Elementy DOM
            this.container = element;
            this.conversation = this.container.find('.bc-assistant-conversation');
            this.textInput = this.container.find('.bc-assistant-text');
            this.sendButton = this.container.find('.bc-assistant-send');
            this.typingIndicator = this.container.find('.bc-assistant-typing');
            this.errorDisplay = this.container.find('.bc-assistant-error');
            
            // Dane kontekstowe
            this.context = this.container.attr('data-context') || 'default';
            this.procedureName = this.container.attr('data-procedure') || '';
            
            // Historia konwersacji
            this.conversationHistory = [];
            
            // Dodaj początkową wiadomość do historii
            if (this.conversation.find('.bc-assistant-ai').length > 0) {
                const welcomeMessage = this.conversation.find('.bc-assistant-ai:first .bc-assistant-bubble').html();
                this.conversationHistory.push({
                    role: 'assistant',
                    content: this.stripHTML(welcomeMessage)
                });
            }
            
            // Zainicjuj nasłuchiwacze zdarzeń
            this.initEventListeners();
        }
        
        /**
         * Inicjalizuje nasłuchiwacze zdarzeń
         */
        initEventListeners() {
            // Obsługa kliknięcia przycisku wysyłania
            this.sendButton.on('click', () => this.sendMessage());
            
            // Obsługa naciśnięcia Enter (bez Shift)
            this.textInput.on('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
            
            // Automatycznie dostosuj wysokość pola tekstowego
            this.textInput.on('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        }
        
        /**
         * Wysyła wiadomość do API
         */
        sendMessage() {
            const message = this.textInput.val().trim();
            if (!message) return;
            
            // Wyczyść pole tekstowe
            this.textInput.val('');
            this.textInput.css('height', 'auto');
            
            // Dodaj wiadomość użytkownika do interfejsu
            const userMessageHtml = `
                <div class="bc-assistant-message bc-assistant-user">
                    <div class="bc-assistant-bubble">${this.formatMessage(message)}</div>
                </div>
            `;
            this.conversation.append(userMessageHtml);
            this.scrollToBottom();
            
            // Dodaj wiadomość do historii
            this.conversationHistory.push({
                role: 'user',
                content: message
            });
            
            // Pokaż wskaźnik pisania
            this.typingIndicator.show();
            this.errorDisplay.hide();
            
            // Wyślij żądanie do serwera
            $.ajax({
                url: bc_assistant_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'bc_assistant_chat',
                    nonce: bc_assistant_vars.nonce,
                    message: message,
                    conversation: JSON.stringify(this.conversationHistory),
                    context: this.context,
                    procedure_name: this.procedureName
                },
                success: (response) => {
                    // Ukryj wskaźnik pisania
                    this.typingIndicator.hide();
                    
                    if (response.success) {
                        // Pobierz odpowiedź asystenta
                        const assistantResponse = response.data.response;
                        
                        // Dodaj odpowiedź asystenta do interfejsu
                        const assistantMessageHtml = `
                            <div class="bc-assistant-message bc-assistant-ai">
                                <div class="bc-assistant-avatar">
                                    <img src="${this.container.find('.bc-assistant-avatar img').attr('src')}" alt="Asystent">
                                </div>
                                <div class="bc-assistant-bubble">${this.formatMessage(assistantResponse)}</div>
                            </div>
                        `;
                        this.conversation.append(assistantMessageHtml);
                        this.scrollToBottom();
                        
                        // Dodaj odpowiedź do historii
                        this.conversationHistory.push({
                            role: 'assistant',
                            content: assistantResponse
                        });
                    } else {
                        // Wyświetl błąd
                        this.errorDisplay.text(response.data || 'Wystąpił błąd. Spróbuj ponownie.').show();
                    }
                },
                error: () => {
                    this.typingIndicator.hide();
                    this.errorDisplay.text('Błąd połączenia. Spróbuj ponownie.').show();
                }
            });
        }
        
        /**
         * Przewija konwersację na dół
         */
        scrollToBottom() {
            this.conversation.scrollTop(this.conversation[0].scrollHeight);
        }
        
        /**
         * Formatuje wiadomość do wyświetlenia (zastępuje znaki nowej linii, linkuje URLe)
         */
        formatMessage(message) {
            // Zastąp znaki nowej linii tagami <br>
            let formatted = message.replace(/\n/g, '<br>');
            
            // Zamień URLe na linki
            formatted = formatted.replace(
                /(https?:\/\/[^\s]+)/g, 
                '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
            );
            
            return formatted;
        }
        
        /**
         * Usuwa tagi HTML z tekstu
         */
        stripHTML(html) {
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            return tmp.textContent || tmp.innerText || '';
        }
    }
    
    // Klasa bąbelka czatu
    class BCAssistantBubble extends BCAssistant {
        constructor(element) {
            super(element);
            
            // Dodatkowe elementy specyficzne dla bąbelka
            this.bubbleButton = element.find('.bc-assistant-bubble-button');
            this.chatWindow = element.find('.bc-assistant-chat-window');
            this.minimizeButton = element.find('.bc-assistant-minimize');
            this.closeButton = element.find('.bc-assistant-close');
            
            // Inicjalizuj dodatkowe nasłuchiwacze
            this.initBubbleListeners();
            
            // Sprawdź zapisany stan (otwarty/zamknięty)
            this.checkSavedState();
        }
        
        /**
         * Inicjalizuje nasłuchiwacze specyficzne dla bąbelka
         */
        initBubbleListeners() {
            // Otwieranie okna czatu
            this.bubbleButton.on('click', () => this.openChat());
            
            // Minimalizacja okna czatu
            this.minimizeButton.on('click', () => this.closeChat());
            
            // Zamykanie okna czatu
            this.closeButton.on('click', () => this.closeChat());
            
            // Obsługa kliknięcia poza oknem czatu
            $(document).on('click', (e) => {
                if (!this.container.is(e.target) && 
                    this.container.has(e.target).length === 0 && 
                    this.container.hasClass('open')) {
                    this.closeChat();
                }
            });
            
            // Zatrzymaj propagację kliknięć w oknie czatu
            this.chatWindow.on('click', (e) => {
                e.stopPropagation();
            });
        }
        
        /**
         * Otwiera okno czatu
         */
        openChat() {
            this.container.addClass('open');
            this.scrollToBottom();
            this.textInput.focus();
            this.chatWindow.fadeIn(300);
            this.chatInput.focus();
    
       // Obsługa przeciągania okna
            this.makeDraggable(this.chatWindow);
            
makeDraggable(element) {
    if (!element || !element.length) return;
    
    const $header = element.find('.bc-assistant-header');
    let isDragging = false;
    let offsetX, offsetY;
    
    // Zmień kursor nad nagłówkiem
    $header.css('cursor', 'move');
    
    // Dodaj obsługę zdarzeń dla przeciągania
    $header.on('mousedown touchstart', function(e) {
        isDragging = true;
        
        const pageX = e.type === 'mousedown' ? e.pageX : e.originalEvent.touches[0].pageX;
        const pageY = e.type === 'mousedown' ? e.pageY : e.originalEvent.touches[0].pageY;
        
        const elementOffset = element.offset();
        offsetX = pageX - elementOffset.left;
        offsetY = pageY - elementOffset.top;
        
        // Zmień pozycjonowanie na absolute, jeśli jeszcze nie jest
        if (element.css('position') !== 'absolute') {
            const position = element.position();
            element.css({
                'position': 'absolute',
                'z-index': 9999999,
                'left': position.left + 'px',
                'top': position.top + 'px',
                'right': 'auto',
                'bottom': 'auto'
            });
        }
        
        e.preventDefault();
    });
    
    $(document).on('mousemove touchmove', function(e) {
        if (!isDragging) return;
        
        const pageX = e.type === 'mousemove' ? e.pageX : e.originalEvent.touches[0].pageX;
        const pageY = e.type === 'mousemove' ? e.pageY : e.originalEvent.touches[0].pageY;
        
        const windowWidth = $(window).width();
        const windowHeight = $(window).height();
        const elementWidth = element.outerWidth();
        const elementHeight = element.outerHeight();
        
        // Oblicz nowe koordynaty, upewniając się, że okno pozostaje w granicach ekranu
        let newLeft = Math.max(0, Math.min(pageX - offsetX, windowWidth - elementWidth));
        let newTop = Math.max(0, Math.min(pageY - offsetY, windowHeight - elementHeight));
        
        // Ustaw nową pozycję
        element.css({
            'left': newLeft + 'px',
            'top': newTop + 'px'
        });
        
        e.preventDefault();
    });
    
    $(document).on('mouseup touchend', function() {
        isDragging = false;
    });
}

            // Zapisz stan
            if (window.localStorage) {
                localStorage.setItem('bc_assistant_open', '1');
            }
        }
        
        /**
         * Zamyka okno czatu
         */
        closeChat() {
            this.container.removeClass('open');
            
            // Zapisz stan
            if (window.localStorage) {
                localStorage.setItem('bc_assistant_open', '0');
            }
        }
        
        /**
         * Sprawdza zapisany stan czatu
         */
        checkSavedState() {
            if (window.localStorage) {
                const isOpen = localStorage.getItem('bc_assistant_open') === '1';
                if (isOpen) {
                    this.openChat();
                }
            }
        }
    }
    
    // Inicjalizacja po załadowaniu dokumentu
    $(document).ready(function() {
        // Inicjalizuj wbudowane instancje asystenta
        $('.bc-assistant-embedded').each(function() {
            new BCAssistant($(this));
        });
        
        // Inicjalizuj instancje bąbelków
        $('.bc-assistant-bubble-container').each(function() {
            new BCAssistantBubble($(this));
        });
        
        // Dodatkowa inicjalizacja dla elementów dodanych dynamicznie
        $(document).on('bc_assistant_init', function() {
            $('.bc-assistant-embedded:not(.initialized)').each(function() {
                $(this).addClass('initialized');
                new BCAssistant($(this));
            });
            
            $('.bc-assistant-bubble-container:not(.initialized)').each(function() {
                $(this).addClass('initialized');
                new BCAssistantBubble($(this));
            });
        });
    });
    
})(jQuery);
