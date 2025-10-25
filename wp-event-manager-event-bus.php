<?php
/**
 * Event Bus centralizado para comunicação entre módulos do WP Event Manager
 * @package wp-event-manager
 */
class WEM_Event_Bus {
    private static $listeners = array();

    /**
     * Emite um evento para todos os listeners registrados
     * @param string $event
     * @param mixed $data
     */
    public static function emit($event, $data = null) {
        if (isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $callback) {
                if (is_callable($callback)) {
                    call_user_func($callback, $data);
                }
            }
        }
    }

    /**
     * Registra um listener para um evento
     * @param string $event
     * @param callable $callback
     */
    public static function on($event, $callback) {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = array();
        }
        self::$listeners[$event][] = $callback;
    }

    /**
     * Remove todos os listeners de um evento
     * @param string $event
     */
    public static function clear($event) {
        if (isset(self::$listeners[$event])) {
            unset(self::$listeners[$event]);
        }
    }
}

// Exemplo de uso:
// WEM_Event_Bus::on('bookmarks_ready', 'wpem_init_osm_markers');
// WEM_Event_Bus::emit('bookmarks_ready', $bookmarks);
