<?php
require_once __DIR__ . '/Database.php';

class MenuRepo {
    private PDO $db;

    public function __construct() {
        $this->db = Database::get();
    }

    public function getCategoriesWithItems(): array {
        $categories = $this->db->query('SELECT * FROM categories ORDER BY sort_order')->fetchAll();
        $items = $this->db->query(
            'SELECT * FROM menu_items WHERE is_available = 1'
        )->fetchAll();

        foreach ($categories as &$cat) {
            $cat['items'] = array_values(array_filter($items, fn($i) => $i['category_id'] == $cat['id']));
        }
        return $categories;
    }
}
