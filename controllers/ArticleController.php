<?php
require_once _WIKIDIR_ . '/models/Article.php';

class ArticleController {
    private $article;

    public function __construct() {
        $this->article = new Article();
    }

    public function index() {
        $query = trim($_GET['search'] ?? '');
        $sort = trim($_GET['sort'] ?? 'created_at');
        $articles = $this->article->search($query, $sort);
        $missing_autolinks = $this->article->getRandomMissingAutolinks(10);
        require_once _WIKIDIR_ . 'views/article.php';
    }

    public function view($id) {
        $article = $this->article->getById($id);
        if ($article) {
            $this->article->incrementViewCount($id);
            $related_articles = $this->article->getRelatedArticles($article['title'], $id);
        } else {
            $related_articles = [];
        }
        require_once _WIKIDIR_ . 'views/article.php';
    }

    public function editor($id = null) {
        $article = $id ? $this->article->getById($id) : null;
        $title = trim($_GET['title'] ?? '');
        $reference_for = trim($_GET['reference_for'] ?? '');
        require_once _WIKIDIR_ . 'views/editor.php';
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $article_id = isset($_POST['article_id']) && !empty($_POST['article_id']) ? (int)$_POST['article_id'] : null;
            $reference_for = trim($_POST['reference_for'] ?? '');
            if (empty($title) || empty($content)) {
                die("Judul dan konten tidak boleh kosong!");
            }
            $article_id = $this->article->save($title, $content, $article_id, $reference_for);
            header('Location: ' . BASE_URL . '/article/view/' . $article_id);
            exit;
        }
    }

    public function revisions($article_id) {
        $article = $this->article->getById($article_id);
        $revisions = $this->article->getRevisions($article_id);
        require_once _WIKIDIR_ . 'views/revision.php';
    }

    public function removeMissingAutolink() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? '');
            if (!empty($title)) {
                $this->article->removeMissingAutolink($title);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Title not provided']);
            }
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }

    public function getRandomMissingAutolinks() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $titles = $this->article->getRandomMissingAutolinks(1);
            echo json_encode(['success' => true, 'titles' => $titles]);
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }

    public function searchAutolink() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $phrase = trim($_POST['phrase'] ?? '');
            if (empty($phrase)) {
                echo json_encode([]);
                exit;
            }
            $results = $this->article->searchByContent($phrase);
            echo json_encode($results);
            exit;
        }
        echo json_encode([]);
        exit;
    }

    public function searchRelevantTitles() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $phrase = trim($_POST['phrase'] ?? '');
            if (empty($phrase)) {
                echo json_encode([]);
                exit;
            }
            $results = $this->article->searchRelevantTitles($phrase);
            echo json_encode($results);
            exit;
        }
        echo json_encode([]);
        exit;
    }

    public function createFromAutolink() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? '');
            $related_title = trim($_POST['related_title'] ?? '');
            $reference_for = trim($_POST['reference_for'] ?? '');
            if (empty($title) || empty($related_title)) {
                die("Judul atau artikel terkait tidak boleh kosong!");
            }
            $content = "Artikel ini merujuk ke [[" . htmlspecialchars($related_title) . "]]";
            $article_id = $this->article->save($title, $content, null, $reference_for);
            if ($article_id) {
                $this->article->removeMissingAutolink($title);
                header('Location: ' . BASE_URL . '/article/view/' . $article_id);
            } else {
                die("Gagal membuat artikel!");
            }
            exit;
        }
        die("Permintaan tidak valid!");
    }

    public function checkDoubleColon($string) {
        if (strpos($string, '::') !== false) {
            return true; // Mengembalikan true jika ditemukan
        }
        return false; // Mengembalikan false jika tidak ditemukan
    }

    public function checkAutolink() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? '');
            error_log("checkAutolink called with title: $title"); // Debug log
            if (empty($title)) {
                echo json_encode(['exists' => false, 'similar_articles' => []]);
                exit;
            }
            if ($this->checkDoubleColon($title)) {
                $parts = explode("::", $title);
                $id = $parts[1];
                $article = $this->article->getById($id);
            } else {
                $article = $this->article->getByTitle($title);
            }
            if ($article) {
                echo json_encode(['exists' => true, 'id' => $article['id']]);
            } else {
                $similarArticles = $this->article->searchRelevantTitles($title);
                echo json_encode(['exists' => false, 'similar_articles' => $similarArticles]);
            }
            exit;
        }
        echo json_encode(['exists' => false, 'similar_articles' => []]);
        exit;
    }
}
?>