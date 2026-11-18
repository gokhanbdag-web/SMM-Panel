<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, Database};

final class PanelController
{
    private function view(string $title, string $content): void
    {
        $user = Auth::user();
        $flash = $_SESSION['flash'] ?? '';
        unset($_SESSION['flash']);
        $token = $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
        require APP . '/Views/layout.php';
    }

    private function page(string $name, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require APP . '/Views/' . $name . '.php';
        return (string) ob_get_clean();
    }

    public function dispatch(string $r): void
    {
        $p = Database::pdo();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($r === 'logout') {
            Auth::requireLogin();
            if ($method !== 'POST') {
                http_response_code(405);
                header('Allow: POST');
                exit('Çıkış işlemi POST ile yapılmalıdır.');
            }
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            go('login');
        }

        if ($r === 'login' || $r === 'register') {
            $error = '';
            if ($method === 'POST') {
                if ($r === 'login') {
                    if (Auth::login(trim((string) ($_POST['email'] ?? '')), (string) ($_POST['password'] ?? ''))) {
                        go('dashboard');
                    }
                    $error = 'E-posta veya şifre hatalı.';
                } else {
                    $name = trim((string) ($_POST['name'] ?? ''));
                    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
                    $password = (string) ($_POST['password'] ?? '');

                    if ($name === '' || mb_strlen($name) > 120 || !$email || strlen($email) > 190 || strlen($password) < 10) {
                        $error = 'Bilgileri kontrol edin. Şifre en az 10 karakter olmalı.';
                    } else {
                        try {
                            $stmt = $p->prepare("INSERT INTO users(name,email,password_hash,role,status,balance,created_at) VALUES(?,?,?,'user','active',0,NOW())");
                            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                            $_SESSION['flash'] = 'Hesabınız oluşturuldu.';
                            go('login');
                        } catch (\PDOException $e) {
                            if ($e->getCode() === '23000') {
                                $error = 'Bu e-posta adresi zaten kayıtlı.';
                            } else {
                                error_log((string) $e);
                                $error = 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.';
                            }
                        }
                    }
                }
            }
            $this->view($r === 'login' ? 'Giriş' : 'Kayıt', $this->page('auth', ['mode' => $r, 'error' => $error]));
            return;
        }

        Auth::requireLogin();
        $uid = (int) Auth::user()['id'];

        if ($r === 'dashboard') {
            $stmt = $p->prepare('SELECT balance FROM users WHERE id=?');
            $stmt->execute([$uid]);
            $balance = $stmt->fetchColumn();
            $stmt = $p->prepare('SELECT o.*,s.name service FROM orders o JOIN services s ON s.id=o.service_id WHERE o.user_id=? ORDER BY o.id DESC LIMIT 10');
            $stmt->execute([$uid]);
            $this->view('Kontrol paneli', $this->page('dashboard', ['balance' => $balance, 'orders' => $stmt->fetchAll()]));
            return;
        }

        if ($r === 'services') {
            $error = '';
            if ($method === 'POST') {
                try {
                    $serviceId = filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT);
                    $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
                    $link = trim((string) ($_POST['link'] ?? ''));
                    $url = filter_var($link, FILTER_VALIDATE_URL);
                    $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));

                    if (!$serviceId || !$quantity || !$url || !in_array($scheme, ['http', 'https'], true)) {
                        throw new \RuntimeException('Sipariş bilgileri geçersiz.');
                    }

                    $p->beginTransaction();
                    $stmt = $p->prepare('SELECT * FROM services WHERE id=? AND active=1 FOR UPDATE');
                    $stmt->execute([$serviceId]);
                    $service = $stmt->fetch();

                    if (!$service || $quantity < (int) $service['min_qty'] || $quantity > (int) $service['max_qty']) {
                        throw new \RuntimeException('Seçilen hizmet veya miktar geçersiz.');
                    }

                    $charge = round($quantity * (float) $service['price_per_1000'] / 1000, 2);
                    if ($charge <= 0) {
                        throw new \RuntimeException('Sipariş tutarı geçersiz.');
                    }

                    $stmt = $p->prepare('SELECT balance FROM users WHERE id=? FOR UPDATE');
                    $stmt->execute([$uid]);
                    if ((float) $stmt->fetchColumn() < $charge) {
                        throw new \RuntimeException('Yetersiz bakiye.');
                    }

                    $p->prepare('UPDATE users SET balance=balance-? WHERE id=?')->execute([$charge, $uid]);
                    $p->prepare("INSERT INTO orders(user_id,service_id,link,quantity,charge,status,created_at) VALUES(?,?,?,?,?,'pending',NOW())")
                        ->execute([$uid, $service['id'], $link, $quantity, $charge]);
                    $p->prepare("INSERT INTO transactions(user_id,amount,type,description,created_at) VALUES(?,?,'debit','Sipariş oluşturuldu',NOW())")
                        ->execute([$uid, -$charge]);
                    $p->commit();

                    $_SESSION['flash'] = 'Sipariş oluşturuldu.';
                    go('orders');
                } catch (\RuntimeException $e) {
                    if ($p->inTransaction()) {
                        $p->rollBack();
                    }
                    $error = $e->getMessage();
                } catch (\Throwable $e) {
                    if ($p->inTransaction()) {
                        $p->rollBack();
                    }
                    error_log((string) $e);
                    $error = 'Sipariş kaydedilirken beklenmeyen bir hata oluştu.';
                }
            }

            $rows = $p->query('SELECT * FROM services WHERE active=1 ORDER BY category,name')->fetchAll();
            $this->view('Hizmetler', $this->page('services', ['services' => $rows, 'error' => $error]));
            return;
        }

        if ($r === 'orders') {
            $stmt = $p->prepare('SELECT o.*,s.name service FROM orders o JOIN services s ON s.id=o.service_id WHERE o.user_id=? ORDER BY o.id DESC');
            $stmt->execute([$uid]);
            $this->view('Siparişlerim', $this->page('orders', ['orders' => $stmt->fetchAll()]));
            return;
        }

        if ($r === 'topup') {
            $error = '';
            if ($method === 'POST') {
                $amountRaw = trim((string) ($_POST['amount'] ?? ''));
                if (!preg_match('/^\d{1,6}(?:\.\d{1,2})?$/', $amountRaw)) {
                    $error = 'Geçerli bir tutar girin.';
                } else {
                    $amount = (float) $amountRaw;
                    if ($amount < 10 || $amount > 100000) {
                        $error = 'Yükleme tutarı 10 TL ile 100.000 TL arasında olmalıdır.';
                    } else {
                        $note = trim((string) ($_POST['note'] ?? ''));
                        if (mb_strlen($note) > 500) {
                            $error = 'Açıklama en fazla 500 karakter olabilir.';
                        } else {
                            $p->prepare("INSERT INTO topups(user_id,amount,method,status,note,created_at) VALUES(?,?,'bank','pending',?,NOW())")
                                ->execute([$uid, $amount, $note]);
                            $_SESSION['flash'] = 'Banka transfer bildiriminiz kaydedildi.';
                            go('topup');
                        }
                    }
                }
            }

            $stmt = $p->prepare('SELECT * FROM topups WHERE user_id=? ORDER BY id DESC');
            $stmt->execute([$uid]);
            $this->view('Bakiye yükle', $this->page('topup', ['rows' => $stmt->fetchAll(), 'error' => $error]));
            return;
        }

        if ($r === 'admin') {
            Auth::admin();
            if ($method === 'POST') {
                $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
                $action = (string) ($_POST['action'] ?? '');
                if (!$id || !in_array($action, ['approve', 'reject'], true)) {
                    http_response_code(400);
                    exit('Geçersiz yönetim işlemi.');
                }

                try {
                    $p->beginTransaction();
                    $stmt = $p->prepare("SELECT * FROM topups WHERE id=? AND status='pending' FOR UPDATE");
                    $stmt->execute([$id]);
                    $topup = $stmt->fetch();

                    if ($topup) {
                        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
                        $p->prepare('UPDATE topups SET status=? WHERE id=?')->execute([$newStatus, $id]);
                        if ($action === 'approve') {
                            $p->prepare('UPDATE users SET balance=balance+? WHERE id=?')->execute([$topup['amount'], $topup['user_id']]);
                            $p->prepare("INSERT INTO transactions(user_id,amount,type,description,created_at) VALUES(?,?,'credit','Banka yüklemesi',NOW())")
                                ->execute([$topup['user_id'], $topup['amount']]);
                        }
                    }
                    $p->commit();
                    $_SESSION['flash'] = $topup ? 'Bakiye bildirimi işlendi.' : 'Bildirim daha önce işlenmiş veya bulunamadı.';
                    go('admin');
                } catch (\Throwable $e) {
                    if ($p->inTransaction()) {
                        $p->rollBack();
                    }
                    error_log((string) $e);
                    http_response_code(500);
                    exit('Bakiye bildirimi işlenemedi.');
                }
            }

            $topups = $p->query("SELECT t.*,u.email FROM topups t JOIN users u ON u.id=t.user_id WHERE t.status='pending' ORDER BY t.id ASC")->fetchAll();
            $orders = $p->query('SELECT o.*,u.email,s.name service FROM orders o JOIN users u ON u.id=o.user_id JOIN services s ON s.id=o.service_id ORDER BY o.id DESC LIMIT 40')->fetchAll();
            $this->view('Yönetim', $this->page('admin', ['topups' => $topups, 'orders' => $orders]));
            return;
        }

        http_response_code(404);
        $this->view('Bulunamadı', '<section class="card">Sayfa bulunamadı.</section>');
    }
}
