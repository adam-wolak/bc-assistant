<?php
/**
 * BC Assistant - Plik konfiguracyjny
 * 
 * Ten plik zawiera konfigurację dla wtyczki BC Assistant.
 * WAŻNE: Ten plik powinien mieć odpowiednie uprawnienia dostępu (644 lub 640)
 * oraz być poza publicznym katalogiem WWW.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Klasa konfiguracyjna dla BC Assistant
class BC_Assistant_Config {
    // Dostępne modele AI
    public static $available_models = array(
        'gpt-4o' => 'GPT-4o (flagowy model)',
        'gpt-4-turbo' => 'GPT-4 Turbo',
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        'claude-3-opus-20240229' => 'Claude 3 Opus',
        'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
        'claude-3-haiku-20240307' => 'Claude 3 Haiku'
    );
    
    // Domyślne ustawienia
private static $defaults = array(
    'model' => 'gpt-4o',
    'system_message_default' => 'Jesteś profesjonalnym i pomocnym asystentem Bielsko Clinic – nowoczesnej kliniki medycyny estetycznej, kosmetologii i laseroterapii. Twoim zadaniem jest odpowiadanie na pytania użytkowników w sposób konkretny, uprzejmy i oparty na wiedzy ze stron kliniki:
https://bielskoclinic.pl/laseroterapia/
https://bielskoclinic.pl/kosmetologia/
https://bielskoclinic.pl/medycyna-estetyczna/
https://bielskoclinic.pl/przeciwwskazania/
https://bielskoclinic.pl/twoj-problem/
https://bielskoclinic.pl/cennik/
Użytkownicy często zadają pytania w trakcie jazdy samochodem lub w biegu – Twoje odpowiedzi powinny być krótkie, klarowne i brzmieć naturalnie, jak rozmowa z konsultantką. Unikaj czytania całych akapitów z tekstu – streszczaj najważniejsze informacje.
Pomagaj użytkownikom:
– dobrać odpowiedni zabieg do ich problemu skórnego,
– zrozumieć efekty, przygotowanie do zabiegu i możliwe przeciwwskazania,
– poznać zakres usług i ceny,
– ustalić czy dany lek (np. DicloDuo Combi) jest przeciwwskazaniem – porównaj jego substancje czynne z listą na stronie przeciwwskazania lub wiedzą medyczną,
– uzyskać kontakt z recepcją: tel. +48 123 456 789
Jeśli użytkownik poprosi o pomoc w umówieniu wizyty, **nie zapisuj go** – poinformuj, że może zadzwonić do recepcji. Jeśli używa głosu, możesz zaproponować: „Czy chcesz, żebym połączył Cię z recepcją?" – aplikacja powinna wtedy wywołać funkcję połączenia (`tel:+48123456789`).
Nie zgaduj. Jeśli nie masz pewności, wyjaśnij dlaczego. W razie potrzeby dopytaj o nazwę leku, problem, oczekiwania lub wiek skóry.
W każdej odpowiedzi delikatnie podkreśl profesjonalizm Bielsko Clinic i zaproś do kontaktu.',
    'system_message_procedure' => 'Jesteś pomocnym asystentem Bielsko Clinic. Udzielasz informacji o zabiegu: {PROCEDURE_NAME}. Odpowiadaj krótko, rzeczowo i profesjonalnie na temat tego zabiegu, jego przebiegu, efektów, ceny i przeciwwskazań.',
    'system_message_contraindications' => 'Jesteś pomocnym asystentem Bielsko Clinic specjalizującym się w przeciwwskazaniach do zabiegów. Zapytaj użytkownika jakie leki przyjmuje, a następnie poinformuj go czy mogą one stanowić przeciwwskazanie do zabiegów oferowanych w klinice. Bądź dokładny i rzeczowy. Pamiętaj, że DicloDuo (diklofenak) NIE jest antybiotykiem, ale niesteroidowym lekiem przeciwzapalnym.',
    'system_message_prices' => 'Jesteś pomocnym asystentem Bielsko Clinic specjalizującym się w cenniku zabiegów. Podaj aktualne ceny zabiegów na podstawie informacji ze strony https://bielskoclinic.pl/cennik/. Podawaj przedziały cenowe, jeśli cena zależy od intensywności lub obszaru zabiegu.',
    'welcome_message_default' => 'Witaj! Jestem asystentem Bielsko Clinic. W czym mogę pomóc odnośnie naszych zabiegów?',
    'welcome_message_procedure' => 'Witaj! Jestem asystentem Bielsko Clinic. Chętnie odpowiem na pytania dotyczące zabiegu {PROCEDURE_NAME}.',
    'welcome_message_contraindications' => 'Dzień dobry! Jestem asystentem Bielsko Clinic specjalizującym się w przeciwwskazaniach. Powiedz jakie leki przyjmujesz, a odpowiem czy stanowią one przeciwwskazanie do zabiegów.',
    'display_mode' => 'bubble', // bubble, embedded lub voice
    'button_text' => 'Zapytaj asystenta',
    'bubble_icon' => 'chat',
    'theme' => 'light',
    'context_detection' => true,
    'use_shadow_dom' => false  // Dodane ustawienie Shadow DOM
);
    
    /**
     * Pobiera wartość konfiguracyjną
     * 
     * @param string $key Klucz konfiguracji
     * @return mixed Wartość konfiguracji
     */
    public static function get($key) {
        // Najpierw sprawdź czy istnieje opcja w bazie danych
		$option_value = get_option('bc_assistant_' . $key, null);
        
		// Jeśli opcja istnieje w bazie danych, użyj jej
		if ($option_value !== null) {
			// Specjalna obsługa dla opcji Shadow DOM - jawne rzutowanie do boolean
			if ($key === 'use_shadow_dom') {
            return (bool)$option_value;
			}
        return $option_value;
    }
        
        // W przeciwnym razie użyj wartości domyślnej
        return isset(self::$defaults[$key]) ? self::$defaults[$key] : null;
    }
    
    /**
     * Ustawia wartość konfiguracyjną
     * 
     * @param string $key Klucz konfiguracji
     * @param mixed $value Wartość do ustawienia
     * @return bool Czy ustawienie się powiodło
     */
    public static function set($key, $value) {
        // Dla modelu przeprowadź sanityzację
        if ($key === 'model') {
            $value = self::sanitize_model($value);
        }
        
        // Zapisz opcję w bazie danych
        $result = update_option('bc_assistant_' . $key, $value);
        
        // Jeśli zapisano model, spróbuj zaktualizować plik .env
        if ($key === 'model' && $result) {
            self::sync_env_file($value);
        }
        
        return $result;
    }
    
    /**
     * Sanityzuje model, aby upewnić się, że jest poprawny
     * 
     * @param string $model Model do sanityzacji
     * @return string Sanityzowany model
     */
    public static function sanitize_model($model) {
        // Sprawdź czy model jest na liście dostępnych modeli
        if (!array_key_exists($model, self::$available_models)) {
            // Logowanie dla debugowania
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BC Assistant: Nieprawidłowy model: ' . $model . '. Użyto domyślnego.');
            }
            
            // Jeśli nie, użyj modelu domyślnego
            return self::$defaults['model'];
        }
        
        return $model;
    }
    
    /**
     * Synchronizuje model z plikiem .env
     * 
     * @param string $model Model do synchronizacji
     * @return bool Czy synchronizacja się powiodła
     */
    private static function sync_env_file($model) {
        // Sprawdź czy plik .env istnieje
        $env_file = ABSPATH . '.env';
        
        if (!file_exists($env_file) || !is_writable($env_file)) {
            return false;
        }
        
        // Wczytaj zawartość pliku
        $env_content = file_get_contents($env_file);
        
        // Zaktualizuj model w pliku .env
        $pattern = '/OPENAI_MODEL=(.+)/';
        $replacement = 'OPENAI_MODEL=' . $model;
        
        if (preg_match($pattern, $env_content)) {
            // Jeśli model już istnieje, zaktualizuj go
            $new_content = preg_replace($pattern, $replacement, $env_content);
        } else {
            // Jeśli nie, dodaj nową linię
            $new_content = rtrim($env_content) . "\nOPENAI_MODEL=" . $model . "\n";
        }
        
        // Zapisz plik
        $result = file_put_contents($env_file, $new_content);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($result !== false) {
                error_log('BC Assistant: Zaktualizowano model w pliku .env: ' . $model);
            } else {
                error_log('BC Assistant: Nie udało się zaktualizować pliku .env.');
            }
        }
        
        return $result !== false;
    }
    
    /**
     * Pobiera wszystkie ustawienia konfiguracyjne
     * 
     * @return array Wszystkie ustawienia
     */
    public static function get_all() {
        $config = array();
        
        foreach (self::$defaults as $key => $default_value) {
            $config[$key] = self::get($key);
        }
        
        return $config;
    }
    
    /**
     * Ustawia konfigurację domyślną podczas aktywacji wtyczki
     */
    public static function set_defaults() {
        foreach (self::$defaults as $key => $value) {
            // Ustaw wartość domyślną tylko jeśli opcja nie istnieje
            if (get_option('bc_assistant_' . $key, null) === null) {
                update_option('bc_assistant_' . $key, $value);
            }
        }
    }
    
    /**
     * Pobiera listę dostępnych modeli
     * 
     * @return array Lista dostępnych modeli
     */
    public static function get_available_models() {
        return self::$available_models;
    }
    
    /**
     * Pobiera aktualnie wybrany model
     * 
     * @return string Aktualnie wybrany model
     */
    public static function get_current_model() {
        return self::get('model');
    }
    
    /**
     * Pobiera nazwę wyświetlaną modelu
     * 
     * @param string $model_key Klucz modelu
     * @return string Nazwa wyświetlana modelu
     */
    public static function get_model_display_name($model_key) {
        return isset(self::$available_models[$model_key]) ? self::$available_models[$model_key] : 'Nieznany model';
    }
}