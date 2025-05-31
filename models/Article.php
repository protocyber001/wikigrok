<?php
require_once _WIKIDIR_ . 'core/Database.php';

class Article {
    private $db;
    private $articles_cache = null; // Cache for articles
    private $title_index = []; // Index by lowercase title
    private $id_index = []; // Index by ID

    public function __construct() {
        $this->db = new Database();
        $this->ensureTables();
    }

    private function ensureTables() {
        if ($this->db->isMysqlAvailable()) {
            $sql = "CREATE TABLE IF NOT EXISTS articles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                created_at VARCHAR(255) DEFAULT '1970-01-01',
                reference_for TEXT,
                view_count INT DEFAULT 0,
                autolink_count INT DEFAULT 0,
                incoming_autolink_count INT DEFAULT 0,
                missing_autolink_count INT DEFAULT 0
            )";
            $this->db->query($sql);

            $sql = "CREATE TABLE IF NOT EXISTS article_revisions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                article_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                revised_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
            )";
            $this->db->query($sql);

            $sql = "CREATE TABLE IF NOT EXISTS missing_autolinks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $this->db->query($sql);
        }
    }

    private function loadArticles() {
        if ($this->articles_cache === null) {
            if ($this->db->isMysqlAvailable()) {
                $result = $this->db->query("SELECT id, title, content, created_at, reference_for, view_count, autolink_count, incoming_autolink_count, missing_autolink_count FROM articles");
                $this->articles_cache = $result->fetch_all(MYSQLI_ASSOC);
            } else {
                $data = $this->db->getFromJson();
                $this->articles_cache = $data['articles'] ?? [];
            }
            // Build indexes
            $this->title_index = [];
            $this->id_index = [];
            foreach ($this->articles_cache as $article) {
                $this->title_index[strtolower($article['title'])] = $article;
                $this->id_index[(int)$article['id']] = $article;
            }
        }
        return $this->articles_cache;
    }

    public function getAll() {
        $articles = $this->loadArticles();
        if ($this->db->isMysqlAvailable()) {
            $this->syncToJson($articles);
        }
        return $articles;
    }

    public function getById($id) {
        if (!is_numeric($id) || $id <= 0) {
            return null;
        }
        $this->loadArticles();
        return $this->id_index[(int)$id] ?? null;
    }

    public function getByTitle($title) {
        if (empty($title)) {
            return null;
        }
        $title = trim($title);
        $this->loadArticles();
        return $this->title_index[strtolower($title)] ?? null;
    }

    public function save($title, $content, $article_id = null, $reference_for = null) {
        $title = trim($title);
        $content = trim($content);
        if (empty($title) || empty($content)) {
            return false;
        }

        // Load articles once
        $articles = $this->loadArticles();

        // Hitung autolink keluar dan missing autolink
        $autolink_count = 0;
        $missing_autolink_count = 0;
        $missing_titles = [];
        preg_match_all('/\[\[([^\]\[]+?)(?:::(\d+))?\]\]/', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $link_title = trim($match[1]);
            $link_id = !empty($match[2]) ? (int)$match[2] : null;
            if ($link_id !== null) {
                $linked_article = $this->getById($link_id);
                if ($linked_article) {
                    $autolink_count++;
                } else {
                    $missing_autolink_count++;
                    $missing_titles[strtolower($link_title)] = $link_title;
                }
            } else {
                $linked_article = $this->getByTitle($link_title);
                if ($linked_article) {
                    $autolink_count++;
                } else {
                    $missing_autolink_count++;
                    $missing_titles[strtolower($link_title)] = $link_title;
                }
            }
        }

        // Perbarui missing_autolinks
        $this->updateMissingAutolinks($missing_titles, $article_id);

        if ($this->db->isMysqlAvailable()) {
            if ($article_id) {
                $current = $this->getById($article_id);
                if ($current) {
                    $this->db->query(
                        "INSERT INTO article_revisions (article_id, title, content) VALUES (?, ?, ?)",
                        [$article_id, $current['title'], $current['content']]
                    );
                    $this->db->query(
                        "UPDATE articles SET title = ?, content = ?, reference_for = ?, view_count = ?, autolink_count = ?, incoming_autolink_count = ?, missing_autolink_count = ? WHERE id = ?",
                        [$title, $content, $reference_for, $current['view_count'], $autolink_count, $current['incoming_autolink_count'], $missing_autolink_count, $article_id]
                    );
                } else {
                    return false;
                }
            } else {
                $this->db->query(
                    "INSERT INTO articles (title, content, created_at, reference_for, view_count, autolink_count, incoming_autolink_count, missing_autolink_count) VALUES (?, ?, ?, ?, 0, ?, 0, ?)",
                    [$title, $content, date('Y-m-d'), $reference_for, $autolink_count, $missing_autolink_count]
                );
                $article_id = $this->db->query("SELECT LAST_INSERT_ID() AS id")->fetch_assoc()['id'];
                
                // Hapus title dari missing_autolinks jika artikel baru dibuat
                $this->db->query("DELETE FROM missing_autolinks WHERE title = ?", [$title]);
                $this->syncMissingAutolinksToJson();
            }

            // Hitung ulang incoming_autolink_count
            $incoming_counts = array_fill_keys(array_keys($this->id_index), 0);
            foreach ($articles as $art) {
                preg_match_all('/\[\[([^\]\[]+?)(?:::(\d+))?\]\]/', $art['content'], $art_matches, PREG_SET_ORDER);
                foreach ($art_matches as $match) {
                    $link_title = trim($match[1]);
                    $link_id = !empty($match[2]) ? (int)$match[2] : null;
                    if ($link_id !== null) {
                        if (isset($this->id_index[$link_id])) {
                            $incoming_counts[$link_id]++;
                        }
                    } else {
                        $linked_article = $this->title_index[strtolower($link_title)] ?? null;
                        if ($linked_article) {
                            $incoming_counts[$linked_article['id']]++;
                        }
                    }
                }
            }
            foreach ($incoming_counts as $id => $count) {
                $this->db->query("UPDATE articles SET incoming_autolink_count = ? WHERE id = ?", [$count, $id]);
            }

            // Perbarui reference_for
            $this->db->query("UPDATE articles SET reference_for = ''");
            foreach ($articles as $art) {
                preg_match_all('/\[\[([^\]\[]+?)(?:::(\d+))?\]\]/', $art['content'], $art_matches, PREG_SET_ORDER);
                foreach ($art_matches as $match) {
                    $link_title = trim($match[1]);
                    $link_id = !empty($match[2]) ? (int)$match[2] : null;
                    $linked_article = $link_id !== null ? $this->getById($link_id) : $this->getByTitle($link_title);
                    if ($linked_article) {
                        $ref_ids = !empty($linked_article['reference_for']) ? explode(',', trim($linked_article['reference_for'], ',')) : [];
                        if (!in_array($art['id'], $ref_ids)) {
                            $ref_ids[] = $art['id'];
                            $new_ref = implode(',', $ref_ids) . ',';
                            $this->db->query("UPDATE articles SET reference_for = ? WHERE id = ?", [$new_ref, $linked_article['id']]);
                        }
                    }
                }
            }

            // Refresh cache
            $this->articles_cache = null;
            $articles = $this->loadArticles();
            $this->syncToJson($articles);
            return $article_id;
        } else {
            $data = $this->db->getFromJson();
            $articles = &$data['articles'] ?? [];

            if ($article_id) {
                $index = null;
                foreach ($articles as $i => $article) {
                    if ($article['id'] == $article_id) {
                        $index = $i;
                        break;
                    }
                }
                if ($index !== null) {
                    $current = $articles[$index];
                    $data['revisions'] = array_merge($data['revisions'] ?? [], [[
                        'id' => count($data['revisions'] ?? []) + 1,
                        'article_id' => $article_id,
                        'title' => $current['title'],
                        'content' => $current['content'],
                        'revised_at' => date('Y-m-d')
                    ]]);
                    $articles[$index] = [
                        'id' => $article_id,
                        'title' => $title,
                        'content' => $content,
                        'created_at' => $current['created_at'],
                        'reference_for' => $reference_for ?? $current['reference_for'] ?? '',
                        'view_count' => $current['view_count'] ?? 0,
                        'autolink_count' => $autolink_count,
                        'incoming_autolink_count' => $current['incoming_autolink_count'] ?? 0,
                        'missing_autolink_count' => $missing_autolink_count
                    ];
                } else {
                    return false;
                }
            } else {
                $article_id = empty($articles) ? 1 : max(array_column($articles, 'id')) + 1;
                $articles[] = [
                    'id' => $article_id,
                    'title' => $title,
                    'content' => $content,
                    'created_at' => date('Y-m-d'),
                    'reference_for' => $reference_for ?? '',
                    'view_count' => 0,
                    'autolink_count' => $autolink_count,
                    'incoming_autolink_count' => 0,
                    'missing_autolink_count' => $missing_autolink_count
                ];

                // Hapus title dari missing_autolinks
                $missing_data = $this->getMissingAutolinksFromJson();
                $missing_data['missing_autolinks'] = array_filter($missing_data['missing_autolinks'], function($t) use ($title) {
                    return strtolower($t) !== strtolower($title);
                });
                $this->saveMissingAutolinksToJson($missing_data);
            }

            // Hitung ulang incoming_autolink_count
            $incoming_counts = array_fill_keys(array_keys($this->id_index), 0);
            foreach ($articles as $art) {
                preg_match_all('/\[\[([^\]\[]+?)(?:::(\d+))?\]\]/', $art['content'], $art_matches, PREG_SET_ORDER);
                foreach ($art_matches as $match) {
                    $link_title = trim($match[1]);
                    $link_id = !empty($match[2]) ? (int)$match[2] : null;
                    if ($link_id !== null) {
                        if (isset($this->id_index[$link_id])) {
                            $incoming_counts[$link_id]++;
                        }
                    } else {
                        $linked_article = $this->title_index[strtolower($link_title)] ?? null;
                        if ($linked_article) {
                            $incoming_counts[$linked_article['id']]++;
                        }
                    }
                }
            }
            foreach ($articles as &$art) {
                $art['incoming_autolink_count'] = $incoming_counts[$art['id']] ?? 0;
            }
            unset($art);

            // Perbarui reference_for
            foreach ($articles as &$art) {
                $art['reference_for'] = '';
            }
            unset($art);
            foreach ($articles as $art) {
                preg_match_all('/\[\[([^\]\[]+?)(?:::(\d+))?\]\]/', $art['content'], $art_matches, PREG_SET_ORDER);
                foreach ($art_matches as $match) {
                    $link_title = trim($match[1]);
                    $link_id = !empty($match[2]) ? (int)$match[2] : null;
                    $linked_article = $link_id !== null ? $this->getById($link_id) : $this->getByTitle($link_title);
                    if ($linked_article) {
                        $ref_ids = !empty($linked_article['reference_for']) ? explode(',', trim($linked_article['reference_for'], ',')) : [];
                        if (!in_array($art['id'], $ref_ids)) {
                            $ref_ids[] = $art['id'];
                            $linked_article['reference_for'] = implode(',', $ref_ids) . ',';
                            foreach ($articles as &$target) {
                                if ($target['id'] == $linked_article['id']) {
                                    $target['reference_for'] = $linked_article['reference_for'];
                                    break;
                                }
                            }
                            unset($target);
                        }
                    }
                }
            }

            $this->db->saveToJson($data);
            // Refresh cache
            $this->articles_cache = null;
            $this->loadArticles();
            return $article_id;
        }
    }

    private function updateMissingAutolinks($missing_titles, $article_id = null) {
        if ($this->db->isMysqlAvailable()) {
            $existing = $this->db->query("SELECT title FROM missing_autolinks")->fetch_all(MYSQLI_ASSOC);
            $existing_titles = array_column($existing, 'title');
            $existing_titles_lower = array_map('strtolower', $existing_titles);

            foreach ($missing_titles as $title) {
                if (!in_array(strtolower($title), $existing_titles_lower)) {
                    $this->db->query("INSERT IGNORE INTO missing_autolinks (title) VALUES (?)", [$title]);
                }
            }

            if ($article_id) {
                $current = $this->getById($article_id);
                if ($current) {
                    preg_match_all('/\[\[([^\]\[]+?)(?:::(\d+))?\]\]/', $current['content'], $matches, PREG_SET_ORDER);
                    $old_autolinks = [];
                    foreach ($matches as $match) {
                        $link_title = trim($match[1]);
                        $link_id = !empty($match[2]) ? (int)$match[2] : null;
                        if ($link_id !== null) {
                            if (!$this->getById($link_id)) {
                                $old_autolinks[] = $link_title;
                            }
                        } else {
                            if (!$this->getByTitle($link_title)) {
                                $old_autolinks[] = $link_title;
                            }
                        }
                    }
                    $new_missing_lower = array_keys($missing_titles);
                    foreach ($old_autolinks as $old_title) {
                        if (!in_array(strtolower($old_title), $new_missing_lower)) {
                            $this->db->query("DELETE FROM missing_autolinks WHERE title = ?", [$old_title]);
                        }
                    }
                }
            }

            $this->syncMissingAutolinksToJson();
        } else {
            $missing_data = $this->getMissingAutolinksFromJson();
            $existing_titles = $missing_data['missing_autolinks'] ?? [];
            $existing_titles_lower = array_map('strtolower', $existing_titles);

            foreach ($missing_titles as $title) {
                if (!in_array(strtolower($title), $existing_titles_lower)) {
                    $existing_titles[] = $title;
                }
            }

            if ($article_id) {
                $current = $this->getById($article_id);
                if ($current) {
                    preg_match_all('/\[\[([^\]\[]+?)(?:::(\d+))?\]\]/', $current['content'], $matches, PREG_SET_ORDER);
                    $old_autolinks = [];
                    foreach ($matches as $match) {
                        $link_title = trim($match[1]);
                        $link_id = !empty($match[2]) ? (int)$match[2] : null;
                        if ($link_id !== null) {
                            if (!$this->getById($link_id)) {
                                $old_autolinks[] = $link_title;
                            }
                        } else {
                            if (!$this->getByTitle($link_title)) {
                                $old_autolinks[] = $link_title;
                            }
                        }
                    }
                    $new_missing_lower = array_keys($missing_titles);
                    $existing_titles = array_filter($existing_titles, function($t) use ($old_autolinks, $new_missing_lower) {
                        return !in_array($t, $old_autolinks) || in_array(strtolower($t), $new_missing_lower);
                    });
                }
            }

            $missing_data['missing_autolinks'] = array_values(array_unique($existing_titles));
            $this->saveMissingAutolinksToJson($missing_data);
        }
    }

    public function getRandomMissingAutolinks($limit = 10) {
        if ($this->db->isMysqlAvailable()) {
            $result = $this->db->query("SELECT title FROM missing_autolinks ORDER BY RAND() LIMIT ?", [$limit]);
            $titles = array_column($result->fetch_all(MYSQLI_ASSOC), 'title');
            return $titles;
        } else {
            $missing_data = $this->getMissingAutolinksFromJson();
            $titles = $missing_data['missing_autolinks'] ?? [];
            shuffle($titles);
            return array_slice($titles, 0, $limit);
        }
    }

    public function removeMissingAutolink($title) {
        if ($this->db->isMysqlAvailable()) {
            $this->db->query("DELETE FROM missing_autolinks WHERE title = ?", [$title]);
            $this->syncMissingAutolinksToJson();
        } else {
            $missing_data = $this->getMissingAutolinksFromJson();
            $missing_data['missing_autolinks'] = array_filter($missing_data['missing_autolinks'], function($t) use ($title) {
                return strtolower($t) !== strtolower($title);
            });
            $this->saveMissingAutolinksToJson($missing_data);
        }
    }

    private function getMissingAutolinksFromJson() {
        $file = _WIKIDIR_ . 'data/autolink.json';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: ['missing_autolinks' => []];
        }
        return ['missing_autolinks' => []];
    }

    private function saveMissingAutolinksToJson($data) {
        file_put_contents(_WIKIDIR_ . 'data/autolink.json', json_encode($data, JSON_PRETTY_PRINT));
    }

    private function syncMissingAutolinksToJson() {
        if ($this->db->isMysqlAvailable()) {
            $result = $this->db->query("SELECT title FROM missing_autolinks")->fetch_all(MYSQLI_ASSOC);
            $data = ['missing_autolinks' => array_column($result, 'title')];
            $this->saveMissingAutolinksToJson($data);
        }
    }

    public function incrementViewCount($id) {
        if (!is_numeric($id) || $id <= 0) {
            return false;
        }
        if ($this->db->isMysqlAvailable()) {
            $this->db->query("UPDATE articles SET view_count = view_count + 1 WHERE id = ?", [$id]);
            $articles = $this->loadArticles();
            $this->syncToJson($articles);
            return true;
        } else {
            $data = $this->db->getFromJson();
            $articles = &$data['articles'] ?? [];
            foreach ($articles as &$article) {
                if ($article['id'] == $id) {
                    $article['view_count'] = ($article['view_count'] ?? 0) + 1;
                    $this->db->saveToJson($data);
                    // Refresh cache
                    $this->articles_cache = null;
                    $this->loadArticles();
                    return true;
                }
            }
            return false;
        }
    }

    public function search($query, $sort = 'created_at') {
        $query = trim($query);
        $allowed_sorts = ['created_at', 'view_count', 'autolinks', 'incoming_autolinks'];
        $sort = in_array($sort, $allowed_sorts) ? $sort : 'created_at';

        if ($this->db->isMysqlAvailable()) {
            $sql = "SELECT id, title, content, created_at, reference_for, view_count, autolink_count, incoming_autolink_count, missing_autolink_count FROM articles WHERE title LIKE ? OR content LIKE ?";
            $params = ["%$query%", "%$query%"];
            if ($sort === 'view_count') {
                $sql .= " ORDER BY view_count DESC";
            } elseif ($sort === 'autolinks') {
                $sql .= " ORDER BY autolink_count DESC";
            } elseif ($sort === 'incoming_autolinks') {
                $sql .= " ORDER BY incoming_autolink_count DESC";
            } else {
                $sql .= " ORDER BY created_at DESC";
            }
            $result = $this->db->query($sql, $params)->fetch_all(MYSQLI_ASSOC);
            return $result ?: [];
        } else {
            $articles = $this->loadArticles();
            if (!empty($query)) {
                $query = strtolower($query);
                $articles = array_filter($articles, function($article) use ($query) {
                    return stripos($article['title'], $query) !== false ||
                           stripos($article['content'], $query) !== false;
                });
            }

            usort($articles, function($a, $b) use ($sort) {
                if ($sort === 'view_count') {
                    return ($b['view_count'] ?? 0) <=> ($a['view_count'] ?? 0);
                } elseif ($sort === 'autolinks') {
                    return ($b['autolink_count'] ?? 0) <=> ($a['autolink_count'] ?? 0);
                } elseif ($sort === 'incoming_autolinks') {
                    return ($b['incoming_autolink_count'] ?? 0) <=> ($a['incoming_autolink_count'] ?? 0);
                } else {
                    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
                }
            });

            return array_values($articles);
        }
    }

    public function getRevisions($article_id) {
        if (!is_numeric($article_id) || $article_id <= 0) {
            return [];
        }
        if ($this->db->isMysqlAvailable()) {
            $result = $this->db->query(
                "SELECT id, article_id, title, content, revised_at FROM article_revisions WHERE article_id = ? ORDER BY revised_at DESC",
                [$article_id]
            )->fetch_all(MYSQLI_ASSOC);
            return $result ?: [];
        } else {
            $data = $this->db->getFromJson();
            $revisions = $data['revisions'] ?? [];
            return array_values(array_filter($revisions, function($revision) use ($article_id) {
                return $revision['article_id'] == $article_id;
            }));
        }
    }

    public function processAutolinks($content) {
        if (empty($content)) {
            return $content;
        }
        return preg_replace_callback(
            '/\[\[([^\]\[]+?)(?:::(\d+))?\]\]/',
            function ($matches) {
                $title = trim($matches[1]);
                $id = !empty($matches[2]) ? (int)$matches[2] : null;
                if ($id !== null) {
                    $article = $this->getById($id);
                    if ($article) {
                        return '<a href="' . BASE_URL . '/article/view/' . $id . '" class="autolink">' . htmlspecialchars($title) . '</a>';
                    }
                    return '<span class="autolink-missing" data-title="' . htmlspecialchars($title) . '">' . htmlspecialchars($title) . '</span>';
                }
                $article = $this->getByTitle($title);
                if ($article) {
                    return '<a href="' . BASE_URL . '/article/view/' . $article['id'] . '" class="autolink">' . htmlspecialchars($title) . '</a>';
                }
                return '<span class="autolink-missing" data-title="' . htmlspecialchars($title) . '">' . htmlspecialchars($title) . '</span>';
            },
            $content
        );
    }

    public function getRelatedArticles($title, $current_id) {
        if (empty($title) || !is_numeric($current_id)) {
            return [];
        }
        $pattern = '[[' . preg_quote(strtolower($title), '/') . ']]';
        if ($this->db->isMysqlAvailable()) {
            $sql = "SELECT id, title, content, created_at, reference_for, view_count, autolink_count, incoming_autolink_count, missing_autolink_count FROM articles WHERE content LIKE ? AND id != ?";
            $result = $this->db->query($sql, ["%$pattern%", $current_id])->fetch_all(MYSQLI_ASSOC);
            return $result ?: [];
        } else {
            $articles = $this->loadArticles();
            return array_values(array_filter($articles, function($article) use ($pattern, $current_id) {
                return $article['id'] != $current_id && strpos(strtolower($article['content']), $pattern) !== false;
            }));
        }
    }

    public function searchByContent($phrase) {
        $phrase = trim($phrase);
        if (empty($phrase)) {
            return [];
        }
        if ($this->db->isMysqlAvailable()) {
            $sql = "SELECT id, title FROM articles WHERE content LIKE ? ORDER BY created_at DESC LIMIT 5";
            $result = $this->db->query($sql, ["%$phrase%"])->fetch_all(MYSQLI_ASSOC);
            return $result ?: [];
        } else {
            $articles = $this->loadArticles();
            $phrase = strtolower($phrase);
            $matches = array_filter($articles, function($article) use ($phrase) {
                return stripos($article['content'], $phrase) !== false;
            });
            $matches = array_map(function($article) {
                return ['id' => $article['id'], 'title' => $article['title']];
            }, $matches);
            return array_slice($matches, 0, 5);
        }
    }

    public function searchRelevantTitles($phrase) {
        $phrase = trim($phrase);
        if (empty($phrase)) {
            return [];
        }
        $words = explode(' ', strtolower($phrase));
        $results = [];

        if ($this->db->isMysqlAvailable()) {
            $title_conditions = array_fill(0, count($words), 'title LIKE ?');
            $title_sql = "SELECT id, title FROM articles WHERE (" . implode(' OR ', $title_conditions) . ") ORDER BY created_at DESC LIMIT 5";
            $title_params = array_map(function($word) { return "%$word%"; }, $words);
            $result = $this->db->query($title_sql, $title_params);
            $results = $result->fetch_all(MYSQLI_ASSOC);

            if (empty($results)) {
                $content_sql = "SELECT id, title FROM articles WHERE content LIKE ? ORDER BY created_at DESC LIMIT 5";
                $results = $this->db->query($content_sql, ["%$phrase%"])->fetch_all(MYSQLI_ASSOC);
            }
        } else {
            $articles = $this->loadArticles();
            $matches = array_filter($articles, function($article) use ($words) {
                $title = strtolower($article['title']);
                foreach ($words as $word) {
                    if (stripos($title, $word) !== false) {
                        return true;
                    }
                }
                return false;
            });

            $results = array_map(function($article) {
                return ['id' => $article['id'], 'title' => $article['title']];
            }, $matches);
            $results = array_slice($results, 0, 5);

            if (empty($results)) {
                $matches = array_filter($articles, function($article) use ($phrase) {
                    return stripos($article['content'], $phrase) !== false;
                });
                $results = array_map(function($article) {
                    return ['id' => $article['id'], 'title' => $article['title']];
                }, $matches);
                $results = array_slice($matches, 0, 5);
            }
        }

        return $results;
    }

    private function syncToJson($articles) {
        $data = [
            'articles' => array_map(function($article) {
                return [
                    'id' => (int)$article['id'],
                    'title' => $article['title'],
                    'content' => $article['content'],
                    'created_at' => $article['created_at'],
                    'reference_for' => $article['reference_for'] ?? '',
                    'view_count' => (int)($article['view_count'] ?? 0),
                    'autolink_count' => (int)($article['autolink_count'] ?? 0),
                    'incoming_autolink_count' => (int)($article['incoming_autolink_count'] ?? 0),
                    'missing_autolink_count' => (int)($article['missing_autolink_count'] ?? 0)
                ];
            }, $articles),
            'revisions' => $this->db->query("SELECT id, article_id, title, content, revised_at FROM article_revisions")->fetch_all(MYSQLI_ASSOC)
        ];
        $this->db->saveToJson($data);
    }
}
?>