<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

if (is_file(__DIR__ . $path) && !str_ends_with($path, '.php')) {
    return false;
}

if (isset($_GET["data"])) {
    header('Content-Type: application/json');
    $data = htmlspecialchars($_GET["data"]);
    Movement::move($data);
    exit;
}

class PieceStorage {

    static public string $file = "./storage.json";

    static public function put(string $location, string $pieceName, string $team) : void {
        $content = json_decode(file_get_contents(self::$file), true);
        $content[$location] = ["piece" => $pieceName, "team" => $team];

        file_put_contents(self::$file, json_encode($content));
    }

    static public function getPieceByLocation(string $location) : string|bool {
        $content = json_decode(file_get_contents(self::$file), true);
        return isset($content[$location]["piece"]) ? $content[$location]["piece"] : false;
    }

    static public function getTeamByLocation(string $location) : string|bool {
        $content = json_decode(file_get_contents(self::$file), true);
        return isset($content[$location]["team"]) ? $content[$location]["team"] : false;
    }

    static public function change(string $fromLocation, string $piece, string $toLocation) {
        $content = json_decode(file_get_contents(self::$file), true);
        $content[$toLocation] = ["piece" => $piece, "team" => PieceStorage::getTeamByLocation($fromLocation)];
        $content[$fromLocation] = "free";

        file_put_contents(self::$file, json_encode($content));
    }

}

class ChessBoard {

    public array $row = ["a", "b", "c", "d", "e", "f", "g", "h"];

    public array $column = [1, 2, 3, 4, 5, 6, 7, 8];

    public const TEAM_BLACK = "black";

    public const TEAM_WHITE = "white";

    public const TEAM_FREE = "free";

    public function draw() {
        $this->draw_dashboard();
        echo '<div class="board-frame">';
        echo '<table id="chessboard" width="500px" height="300px" border="0px" cellspacing="0px">';
        $value = 0;
        for($col = 0; $col < 8; $col++) {
            echo "<tr>";
            $value = $col;
            for($row = 0; $row < 8; $row++) {
                $color = ($value % 2 == 0) ? "black" : "white";
                $cellValue = array_reverse($this->row, true)[$row] . "" . array_reverse($this->column)[$col];
                $displayContent = "";
                $pieceName = "free";
                $team = "free";

                if($col < 2) {
                    $team = "black";
                } else if($col >= 6) {
                    $team = "white";
                }

                foreach(JsonTool::getValue("pieces", "./pieces.json") as $piece => $data) {
                    if(in_array($cellValue, $data["location"])) {
                        $displayContent = $data["icon"];
                        $pieceName = $piece;
                        break;
                    }
                }
                echo "<td cellValue=$cellValue team=$team height=60px width=50px bgcolor=$color>" . $displayContent . "</td>";
                PieceStorage::put($cellValue, $pieceName, $team);
                $value++;
            }
            echo "</tr>";
        }
        echo "</table>";
        echo '</div>';
    }

    public function draw_dashboard() {
        echo "<div id='dashboard'>";

        // Team Black Card
        echo "<div class='team-card team-black'>";
        echo "  <div class='team-header'>";
        echo "    <h2>Black</h2>";
        echo "    <span class='player-name'></span>";
        echo "  </div>";
        echo "  <div class='captured-pieces' id='points_black'>".PlayerStats::getBlackPoints()."</div>";
        echo "</div>";

        // Game Info Center (z.B. Zug-Anzeige / Status)
        echo "<div class='game-status'>";
        echo "  <span class='turn-indicator'>00:00</span>";
        echo "</div>";

        // Team White Card
        echo "<div class='team-card team-white'>";
        echo "  <div class='team-header'>";
        echo "    <h2>White</h2>";
        echo "    <span class='player-name'></span>";
        echo "  </div>";
        echo "  <div class='captured-pieces' id='points_white'>".PlayerStats::getWhitePoins()."</div>";
        echo "</div>";

        echo "</div>";
    }

}

class StyleLoader {

    public function loadStyle() {
        echo '<link rel="stylesheet" href="style.css">'.PHP_EOL;
    }

}

class JSLoader {

    public function loadJS() {
        echo '<script src="main.js" type="text/javascript"></script>' . PHP_EOL;
    }

}

class Movement {

    public const MOVE_FRONT_R = "front_r"; // Front Right
    public const MOVE_FRONT_L = "front_l"; // Front Left

    public const MOVE_BACK_R = "back_r"; // Back Right
    public const MOVE_BACK_L = "back_l"; // Back Left

    public const MOVE_FRONT = "front";
    public const MOVE_BACK = "back";

    public const MOVE_LEFT = "left";
    public const MOVE_RIGHT = "right";

    public const MOVE_ATTACK = "attack";

    static public function move(string $raw_data) {
        $from = substr($raw_data, 0, 2);
        $to = substr($raw_data, 2, 2);
        $fromPiece = PieceStorage::getPieceByLocation($from);

        $valid = self::isValid(
            from: $from,
            fromPiece: $fromPiece,
            to: $to,
            toPiece: PieceStorage::getPieceByLocation($to)
        );

        if($valid) {
            echo json_encode([
                "type" => "move_request",
                "from" => $from,
                "to" => $to,
                "piece" => $fromPiece
            ]);
            PieceStorage::change($from, $fromPiece, $to);
        } else {
            echo json_encode([
                "type" => "non_valid_request",
                "from" => $from,
                "to" => $to,
                "piece" => $fromPiece
            ]);
        }
    }

    static public function isValid(string $from, string $fromPiece, string $to, string $toPiece) : bool {
        if (!$fromPiece || $fromPiece === "free") {
            return false;
        }
        $move_directions = JsonTool::getValue("movement.".$fromPiece.".directions", "./pieces.json");
        $move_type = Movement::getMoveType($from, $to);
        foreach(explode("|", $move_directions) as $direction) {
            $max_move_distance = JsonTool::getValue("movement." . $fromPiece . ".movement." . $direction, "./pieces.json");
            if($move_type["move"] == $direction && $move_type["distance"] <= $max_move_distance) {
                if(PieceStorage::getTeamByLocation($to) !== PieceStorage::getTeamByLocation($from)) {
                    if(PieceStorage::getTeamByLocation($to) != "free") {
                        PlayerStats::addPoint(
                            PieceStorage::getPieceByLocation($to),
                            PieceStorage::getTeamByLocation($from)
                        );
                    }
                    return true;
                }
                return false;
            }

            // attack key
            $attack_key = JsonTool::getValue("movement." . $fromPiece . ".attack", "./pieces.json");

            if($attack_key !== NULL) {
                foreach(explode("|", $attack_key["movement"]) as $attack_move) {
                    if($move_type["move"] == $attack_move && $move_type["distance"] <= $attack_key["distance"]) {
                        if (PieceStorage::getTeamByLocation($to) !== PieceStorage::getTeamByLocation($from)) {
                            if (PieceStorage::getTeamByLocation($to) != "free") {
                                PlayerStats::addPoint(
                                    PieceStorage::getPieceByLocation($to),
                                    PieceStorage::getTeamByLocation($from)
                                );
                            }
                            return true;
                        }
                        return false;
                    }
                }
            }
        }
        return false;
    }

    static public function getColumn(string $location) {
        return $location[0];
    }

    static public function getRow(string $location) {
        return $location[1];
    }

    static public function getMoveType(string $from, string $to) : array {
        (int) $row_from = Movement::getRow($from); // zahl
        (string) $column_from = Movement::getColumn($from); // buchstabe

        (int) $row_to = Movement::getRow($to); // zahl
        (string) $column_to = Movement::getColumn($to); // buchstabe

        $move_data = ["move" => "?", "distance" => "?", "team" => "?", "piece" => NULL, "possible_attack" => false];

        if(PieceStorage::getTeamByLocation($from) == ChessBoard::TEAM_WHITE) {
            // Vorne/Hinten Züge
            if ($column_to === $column_from && $row_to !== $row_from) {
                $move_data = [
                    "move"     => $row_to > $row_from ? Movement::MOVE_FRONT : Movement::MOVE_BACK,
                    "distance" => abs($row_to - $row_from),
                    "team"     => ChessBoard::TEAM_WHITE
                ];
            }
            // Quere Züge
            if (abs(ord($column_to) - ord($column_from)) === abs($row_to - $row_from) && $column_to !== $column_from) {
                $isForward = $row_to > $row_from;
                $isRight   = ord($column_to) > ord($column_from);

                if ($isForward) {
                    $move = $isRight ? Movement::MOVE_FRONT_R : Movement::MOVE_FRONT_L;
                } else {
                    $move = $isRight ? Movement::MOVE_BACK_R : Movement::MOVE_BACK_L;
                }

                $move_data = [
                    "move"     => $move,
                    "distance" => abs($row_to - $row_from),
                    "team"     => ChessBoard::TEAM_WHITE
                ];
            }
            // Links Rechts Züge
            if($row_to == $row_from && $column_to !== $column_from) {
                $isRight = ord($column_to) > ord($column_from);
                
                $move = $isRight ? Movement::MOVE_RIGHT : Movement::MOVE_LEFT;
                $move_data = [
                    "move" => $move,
                    "distance" => abs(ord($column_to) - ord($column_from)),
                    "team" => ChessBoard::TEAM_WHITE
                ];

            }
        } else if(PieceStorage::getTeamByLocation($from) == ChessBoard::TEAM_BLACK) {
            // Gerade Züge
            if ($column_to === $column_from && $row_to !== $row_from) {
                $move_data = [
                    "move"     => $row_to < $row_from ? Movement::MOVE_FRONT : Movement::MOVE_BACK,
                    "distance" => abs($row_to - $row_from),
                    "team"     => ChessBoard::TEAM_BLACK
                ];
            }
            // Quere Züge
            if (abs(ord($column_to) - ord($column_from)) === abs($row_to - $row_from) && $column_to !== $column_from) {
                $isForward = $row_to < $row_from;
                $isRight   = ord($column_to) > ord($column_from);

                $move_data = [
                    "move"     => $isForward
                        ? ($isRight ? Movement::MOVE_FRONT_R : Movement::MOVE_FRONT_L)
                        : ($isRight ? Movement::MOVE_BACK_R  : Movement::MOVE_BACK_L),
                    "distance" => abs($row_to - $row_from),
                    "team"     => ChessBoard::TEAM_BLACK
                ];
            }
            // Links Rechts Züge
            if ($row_to == $row_from && $column_to !== $column_from) {
                $isRight = ord($column_to) < ord($column_from);

                $move = $isRight ? Movement::MOVE_RIGHT : Movement::MOVE_LEFT;
                $move_data = [
                    "move" => $move,
                    "distance" => abs(ord($column_to) - ord($column_from)),
                    "team" => ChessBoard::TEAM_WHITE
                ];
            }
        }

        $move_data["piece"] = PieceStorage::getPieceByLocation($from);
        if (PieceStorage::getTeamByLocation($from) !== PieceStorage::getTeamByLocation($to) && PieceStorage::getTeamByLocation($to) !== "free") {
            $move_data["possible_attack"] = true;
        } else {
            $move_data["possible_attack"] = false;
        }

        Logger::log("[MOVE: ".$move_data["move"]."] ".$from." -> ". $to. " Distance: ". $move_data["distance"] . " Team: ". $move_data["team"] . " Piece: " . $move_data["piece"] . " Possible Attack: ".$move_data["possible_attack"]);

        return $move_data;
    }

}

class JsonTool {

    public static function getValue(string $key, string $path): mixed {
        $data = json_decode(file_get_contents($path), true);
        foreach (explode(".", $key) as $part) {
            if (!array_key_exists($part, $data)) {
                return null;
            }

            $data = $data[$part];
        }
        return $data;
    }
}

class PlayerStats {

    static public string $file = "./stats.json";

    static public array $points = [
        "the_pawn" => 1,
        "the_knight" => 3,
        "the_bishop" => 3,
        "the_rook" => 5,
        "the_queen" => 9,
        "free" => 0,
        "the_king" => 0 // Spiel sollte enden
    ];

    static public function addPoint(string $piece, string $team) {
        if(empty($piece)) {
            return;
        }
        $content = json_decode(file_get_contents(self::$file), true);
        $current_points = $content[$team]["points"];
        $content[$team]["points"] = $current_points + self::$points[$piece];
        file_put_contents(self::$file, json_encode($content));
    }

    static public function getWhitePoins() {
        $content = json_decode(file_get_contents(self::$file), true);
        return $content["white"]["points"];
    }

    static public function getBlackPoints() {
        $content = json_decode(file_get_contents(self::$file), true);
        return $content["black"]["points"];
    }

}

class Logger {

    static public string $file = "./logger.txt";

    static public function log(string $content) {
        file_put_contents(self::$file, $content . PHP_EOL, FILE_APPEND);
    }

}

echo '<!DOCTYPE html>';
echo '<html lang="de">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>Schachbrett</title>';
echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">';

$style = new StyleLoader();
$style->loadStyle();

echo '</head>';
echo '<body>';

echo '<header class="site-header">';
echo '<div class="site-header__text">';
echo '<h1>Chess</h1>';
echo '<p>Khaled Daghstani</p>';
echo "<button type='button'>New Game</button>";
echo '</div>';
echo '</header>';

echo '<main class="site-main">';

$chessBoard = new ChessBoard();
$chessBoard->draw();

echo '</main>';

echo '<footer class="site-footer">';
echo '<p>Made in Munich by Khaled Daghstani</p>';
echo '</footer>';

$js = new JSLoader();
$js->loadJS();

echo '</body>';
echo '</html>';
