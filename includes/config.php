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
    // Domyślne ustawienia
    private static $defaults = array(
        'api_key' => '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',
        'model' => 'gpt-4o',
        'system_message_default' => 'Jesteś pomocnym asystentem Bielsko Clinic, który odpowiada na pytania dotyczące zabiegów i usług kliniki. Odpowiadaj krótko, rzeczowo i profesjonalnie.',
        'system_message_procedure' => 'Jesteś pomocnym asystentem Bielsko Clinic. Udzielasz informacji o zabiegu: {PROCEDURE_NAME}. Odpowiadaj krótko, rzeczowo i profesjonalnie na temat tego zabiegu, jego przebiegu, efektów, ceny i przeciwwskazań.',
        'system_message_contraindications' => 'Jesteś pomocnym asystentem Bielsko Clinic specjalizującym się w przeciwwskazaniach do zabiegów. Zapytaj użytkownika jakie leki przyjmuje, a następnie poinformuj go czy mogą one stanowić przeciwwskazanie do zabiegów oferowanych w klinice. Bądź dokładny i rzeczowy.',
        'welcome_message_default' => 'Witaj! Jestem asystentem Bielsko Clinic. W czym mogę pomóc odnośnie naszych zabiegów?',
        'welcome_message_procedure' => 'Witaj! Jestem asystentem Bielsko Clinic. Chętnie odpowiem na pytania dotyczące zabiegu {PROCEDURE_NAME}.',
        'welcome_message_contraindications' => 'Dzień dobry! Jestem asystentem Bielsko Clinic specjalizującym się w przeciwwskazaniach. Powiedz jakie leki przyjmujesz, a odpowiem czy stanowią one przeciwwskazanie do zabiegów.',
        'display_mode' => 'bubble', // bubble lub embedded
        'button_text' => 'Zapytaj asystenta',
        'bubble_icon' => 'chat',
        'theme' => 'light',
        'context_detection' => true
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
        return update_option('bc_assistant_' . $key, $value);
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
}
