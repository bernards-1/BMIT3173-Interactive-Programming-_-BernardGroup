<?php
// Controllers/LoginController.php
require_once __DIR__ . '/../Models/User.php';

class LoginController {
    /**
     * Handle the login and logout requests (Page Controller Pattern).
     * 
     * @return string|null Error message if validation fails, null otherwise.
     */
    public function handleLoginRequest() {
        // Handle Logout trigger
        if (isset($_GET['logout'])) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_destroy();
            header('Location: login.php');
            exit;
        }

        // Handle POST submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            
            if (empty($email) || empty($password)) {
                return "Please fill in all fields.";
            } else {
                // Fetch user via ORM Model
                $user = User::findByEmail($email);
                
                if ($user && password_verify($password, $user->password)) {
                    if (!$user->is_active) {
                        return "Your account is not active. Please check your email for the activation link.";
                    }
                    // Password is correct, start session
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $_SESSION['user_id'] = $user->user_id;
                    $_SESSION['role'] = $user->role;
                    $_SESSION['user'] = [
                        'user_id' => $user->user_id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'role' => $user->role
                    ];
                    
                    // Redirect based on role
                    switch ($user->role) {
                        case 'doctor':
                            header('Location: ../Doctor/mainpage.php');
                            break;
                        case 'admin':
                            header('Location: ../Admin/Admin.php');
                            break;
                        case 'patient':
                            header('Location: ../Patient/mainpage.php');
                            break;
                        case 'pharmacist':
                            header('Location: ../Pharmacy/mainpage.php');
                            break;
                        default:
                            header('Location: ../Doctor/mainpage.php');
                            break;
                    }
                    exit;
                } else {
                    return "Invalid email or password.";
                }
            }
        }
        
        return null;
    }
}
