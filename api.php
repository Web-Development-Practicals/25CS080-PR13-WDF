
<?php
header("Content-Type: application/json; charset=UTF-8");

require_once "db.php";

function respond($status, $message, $data = null, $code = 200) {
    http_response_code($code);
    $result = ["status" => $status, "message" => $message];

    if ($data !== null) {
        $result["data"] = $data;
    }

    echo json_encode($result);
    exit;
}

$action = $_GET["action"] ?? "";
$method = $_SERVER["REQUEST_METHOD"];

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = [];
}

try {
    switch ($action) {
        case "get_menu":
            $role = $_GET["role"] ?? "user";

            if (!in_array($role, ["user", "editor", "admin"], true)) {
                respond("error", "Invalid role.", null, 400);
            }

            $allowed = [
                "user" => ["user"],
                "editor" => ["user", "editor"],
                "admin" => ["user", "editor", "admin"]
            ];

            $marks = implode(",", array_fill(
                0, count($allowed[$role]), "?"
            ));

            $stmt = $pdo->prepare(
                "SELECT title, link FROM menu_items
                 WHERE min_role IN ($marks) ORDER BY id"
            );
            $stmt->execute($allowed[$role]);

            respond("success", "Menu loaded.", $stmt->fetchAll());
            break;

        case "read":
            if ($method !== "GET") {
                respond("error", "GET method required.", null, 405);
            }

            $stmt = $pdo->query(
                "SELECT id, name, category, price
                 FROM items ORDER BY id DESC"
            );

            respond("success", "Items loaded.", $stmt->fetchAll());
            break;

        case "create":
            if ($method !== "POST") {
                respond("error", "POST method required.", null, 405);
            }

            $name = trim($input["name"] ?? "");
            $category = trim($input["category"] ?? "");
            $price = $input["price"] ?? null;

            if ($name === "" || $category === "" ||
                !is_numeric($price) || !is_finite((float)$price) ||
                (float)$price < 0 || strlen($name) > 100 ||
                strlen($category) > 50) {
                respond("error", "Enter valid item details.", null, 400);
            }

            $stmt = $pdo->prepare(
                "INSERT INTO items (name, category, price)
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$name, $category, $price]);

            respond("success", "Item created successfully.");
            break;

        case "update":
            if ($method !== "POST") {
                respond("error", "POST method required.", null, 405);
            }

            $id = filter_var(
                $input["id"] ?? null, FILTER_VALIDATE_INT
            );
            $name = trim($input["name"] ?? "");
            $category = trim($input["category"] ?? "");
            $price = $input["price"] ?? null;

            if (!$id || $id < 1 || $name === "" ||
                $category === "" || !is_numeric($price) ||
                !is_finite((float)$price) || (float)$price < 0 ||
                strlen($name) > 100 || strlen($category) > 50) {
                respond("error", "Enter valid item details.", null, 400);
            }

            $stmt = $pdo->prepare(
                "UPDATE items SET name = ?, category = ?, price = ?
                 WHERE id = ?"
            );
            $stmt->execute([$name, $category, $price, $id]);

            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare(
                    "SELECT id FROM items WHERE id = ?"
                );
                $check->execute([$id]);

                if (!$check->fetch()) {
                    respond("error", "Item not found.", null, 404);
                }
            }

            respond("success", "Item updated successfully.");
            break;

        case "delete":
            if ($method !== "POST") {
                respond("error", "POST method required.", null, 405);
            }

            $id = filter_var(
                $input["id"] ?? null, FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {
                respond("error", "Valid item ID required.", null, 400);
            }

            $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                respond("error", "Item not found.", null, 404);
            }

            respond("success", "Item deleted successfully.");
            break;

        default:
            respond("error", "Invalid action.", null, 400);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    respond("error", "A database operation failed.", null, 500);
}
?>
