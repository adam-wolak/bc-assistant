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
            this.additionalContext = '';
            
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
            this.checkMobileDisplay();
        }
/**
 * Sprawdza i naprawia wyświetlanie na urządzeniach mobilnych
 */
checkMobileDisplay() {
    // Sprawdź czy jesteśmy na urządzeniu mobilnym
    const isMobile = window.innerWidth < 768 || 
                    /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    if (isMobile) {
        // Zastosuj poprawki dla urządzeń mobilnych
        
        // 1. Upewnij się, że bąbelek jest widoczny
        const $bubbleButton = $('.bc-assistant-bubble-button');
        if ($bubbleButton.length) {
            $bubbleButton.css({
                'display': 'flex',
                'visibility': 'visible',
                'opacity': '1',
                'position': 'fixed',
                'bottom': '10px',
                'right': '10px',
                'z-index': '999999'
            });
        }
        
        // 2. Dostosuj rozmiar i położenie okna czatu
        const $chatWindow = $('.bc-assistant-chat-window');
        if ($chatWindow.length) {
            $chatWindow.css({
                'position': 'fixed',
                'width': '90vw',
                'max-width': '350px',
                'height': '70vh',
                'bottom': '70px',
                'right': '10px',
                'z-index': '999998'
            });
        }
        
        // 3. Upewnij się, że kontener konwersacji ma odpowiednie przewijanie
        const $conversation = this.conversation;
        if ($conversation.length) {
            $conversation.css({
                'max-height': 'calc(70vh - 120px)',
                'overflow-y': 'auto',
                '-webkit-overflow-scrolling': 'touch',
                'overscroll-behavior': 'contain'
            });
        }
        
        // 4. Przenieś bąbelek na koniec body - to pomoże uniknąć problemów z przewijaniem
        const $container = this.container;
        if ($container.hasClass('bc-assistant-bubble-container') && $container.parent().prop('tagName') !== 'BODY') {
            $('body').append($container.detach());
        }
    }
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
            
            // Obsługa zmiany w selektorze BC
            $('.bc-select').on('change', (e) => {
                // Pobierz wartość aktualnie wybranego zabiegu
                const selectedValue = $(e.target).val();
                const selectedText = $(e.target).find('option:selected').text();
                
                if (selectedValue) {
                    // Poczekaj chwilę, aż zawartość zabiegu się załaduje
                    setTimeout(() => {
                        // Znajdź treść wybranego zabiegu
                        const selectedContent = $('#content-' + selectedValue);
                        
                        if (selectedContent.length) {
                            // Pobierz tekst zawartości
                            const contentText = selectedContent.text().trim();
                            
                            // Zapisz jako dodatkowy kontekst
                            this.additionalContext = "Aktualnie wybrany zabieg w selektorze: " + selectedText + 
                                                     "\nTreść wybranego zabiegu: " + contentText;
                            
                            console.log('Zmieniono kontekst asystenta na zabieg: ' + selectedText);
                        }
                    }, 300);
                }
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
                    procedure_name: this.procedureName,
                    additional_context: this.additionalContext
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
        
        /**
         * Pobiera aktualnie wybrany kontekst z selektora BC
         */
        getCurrentSelectorContext() {
            // Sprawdź czy jest aktywny selektor na stronie
            const bcSelect = $('.bc-select');
            if (bcSelect.length === 0) return '';
            
            // Pobierz aktualnie wybrany element
            const selectedValue = bcSelect.val();
            if (!selectedValue) return '';
            
            const selectedText = bcSelect.find('option:selected').text();
            const selectedContent = $('#content-' + selectedValue);
            
            if (selectedContent.length) {
                // Pobierz tekst zawartości
                const contentText = selectedContent.text().trim();
                return "Aktualnie wybrany zabieg w selektorze: " + selectedText + 
                      "\nTreść wybranego zabiegu: " + contentText;
            }
            
            return '';
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
            
            // Pobierz aktualny kontekst z selektora BC
            this.additionalContext = this.getCurrentSelectorContext();
            
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
        // Obsługa zmiany w selektorze BC
        $('.bc-select').on('change', function() {
            // Wywołaj zdarzenie tylko gdy asystent jest zainicjowany
            if ($('.bc-assistant-embedded.initialized').length || $('.bc-assistant-bubble-container.initialized').length) {
                $(document).trigger('bc_selector_changed', [$(this).val(), $(this).find('option:selected').text()]);
            }
        });
        
        // Inicjalizuj wbudowane instancje asystenta
        $('.bc-assistant-embedded').each(function() {
            $(this).addClass('initialized');
            new BCAssistant($(this));
        });
        
        // Inicjalizuj instancje bąbelków
        $('.bc-assistant-bubble-container').each(function() {
            $(this).addClass('initialized');
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
