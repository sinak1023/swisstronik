<?php

/**
 * تنظیمات سراسری قابل ویرایش از پنل (پنل پیامک، توکن بات، شماره مدیر و ...).
 * مقادیر در جدول `settings` نگهداری می‌شوند.
 */
class Settings
{
    private $db;
    private $table = 'settings';
    private static $cache = null;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function get($key, $default = null)
    {
        if (self::$cache === null) {
            $this->loadAll();
        }
        return self::$cache[$key] ?? $default;
    }

    public function all()
    {
        if (self::$cache === null) {
            $this->loadAll();
        }
        return self::$cache;
    }

    private function loadAll()
    {
        self::$cache = [];
        try {
            $rows = $this->db->fetchAll("SELECT skey, svalue FROM {$this->table}");
            foreach ($rows as $r) {
                self::$cache[$r['skey']] = $r['svalue'];
            }
        } catch (Exception $e) {
            self::$cache = [];
        }
    }

    public function set($key, $value)
    {
        $this->db->execute(
            "INSERT INTO {$this->table} (skey, svalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
            [$key, $value]
        );
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    public function setMany($pairs)
    {
        foreach ($pairs as $k => $v) {
            $this->set($k, $v);
        }
    }
}
