<?php
require_once __DIR__ . '/Database.php';

class MenuRepo {
    private $db;

    public function __construct() {
        $this->db = Database::get();
    }

    public function getCategoriesWithItems() {
        $categories = $this->db->query('SELECT * FROM categories ORDER BY sort_order')->fetchAll();
        $items = $this->db->query(
            'SELECT * FROM menu_items WHERE is_available = 1'
        )->fetchAll();

        foreach ($categories as &$cat) {
            $catId = $cat['id'];
            $cat['items'] = array_values(array_filter($items, function($i) use ($catId) {
                return $i['category_id'] == $catId;
            }));
        }
        return $categories;
    }

    public function getItemById($id) {
        $stmt = $this->db->prepare('SELECT * FROM menu_items WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
