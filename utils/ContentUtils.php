<?php
class ContentUtils {
    public static function get($pdo, $page, $key, $default = '') {
        $stmt = $pdo->prepare("SELECT component_value FROM page_content WHERE page_name = ? AND component_key = ?");
        $stmt->execute([$page, $key]);
        $val = $stmt->fetchColumn();
        return $val ?: $default;
    }

    public static function getPage($pdo, $page, $defaults = []) {
        $stmt = $pdo->prepare("SELECT component_key, component_value FROM page_content WHERE page_name = ?");
        $stmt->execute([$page]);
        $content = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_merge($defaults, $content);
    }
}
