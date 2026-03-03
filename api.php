
ini_set('display_errors', 1);
error_reporting(E_ALL);
<?php
// api.php - API REST pour les produits
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Pour autoriser les requêtes depuis index.html
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// --- CONFIGURATION DE LA BASE DE DONNÉES ---
// À MODIFIER SELON VOTRE CONFIGURATION !
$host = '127.0.0.1.';      // Serveur (souvent localhost)
$user = 'Léon';           // Nom d'utilisateur MySQL
$password = 'bomebmwx6';           // MOT DE PASSE MySQL (vide en local par défaut)
$dbname = 'images_db';    // Nom de votre base de données
// -------------------------------------------

// Connexion BDD
$conn = new mysqli($host, $user, $password, $dbname);

// Vérifier la connexion
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Connexion BDD échouée',
        'details' => $conn->connect_error
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Gérer les requêtes OPTIONS (pour CORS)
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

switch ($method) {
    case 'GET':
        // Récupérer tous les produits
        $result = $conn->query("SELECT id, nom, chemin_image, prix FROM produits ORDER BY date_upload DESC");
        
        if (!$result) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur SQL: ' . $conn->error]);
            exit;
        }
        
        $produits = [];
        while ($row = $result->fetch_assoc()) {
            $produits[] = $row;
        }
        echo json_encode($produits);
        break;

    case 'POST':
        // Vérifier les champs requis
        if (!isset($_POST['nom']) || !isset($_POST['prix']) || !isset($_FILES['image'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Champs manquants (nom, prix, image requis)']);
            exit;
        }

        $nom = $conn->real_escape_string($_POST['nom']);
        $prix = floatval($_POST['prix']);

        // Vérifications
        if (empty($nom)) {
            http_response_code(400);
            echo json_encode(['error' => 'Le nom ne peut pas être vide']);
            exit;
        }

        if ($prix <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Le prix doit être supérieur à 0']);
            exit;
        }

        // Gestion de l'upload
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0777, true)) {
                http_response_code(500);
                echo json_encode(['error' => 'Impossible de créer le dossier uploads']);
                exit;
            }
        }

        $imageName = basename($_FILES["image"]["name"]);
        $extension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($extension, $allowed)) {
            http_response_code(400);
            echo json_encode(['error' => 'Format non autorisé. Utilisez: ' . implode(', ', $allowed)]);
            exit;
        }

        if ($_FILES["image"]["size"] > 5 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['error' => 'Fichier trop volumineux (max 5 Mo)']);
            exit;
        }

        // Vérifier que c'est bien une image
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check === false) {
            http_response_code(400);
            echo json_encode(['error' => 'Le fichier n\'est pas une image valide']);
            exit;
        }

        $newFileName = uniqid() . '_' . $imageName;
        $targetFilePath = $targetDir . $newFileName;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
            $sql = "INSERT INTO produits (nom, chemin_image, prix) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssd", $nom, $targetFilePath, $prix);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'id' => $conn->insert_id,
                    'message' => 'Produit ajouté avec succès'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erreur BDD: ' . $conn->error]);
            }
            $stmt->close();
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Échec de l\'upload du fichier']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Méthode non autorisée']);
        break;
}

$conn->close();
?>