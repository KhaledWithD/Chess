document.addEventListener("DOMContentLoaded", () => {
    let clicks = 0;
    let first_click = "";

    document.querySelectorAll("#chessboard td").forEach(cell => {
        cell.addEventListener("click", function () {
            updateStats();
            clicks++;
            if (clicks < 2) {
                first_click = this.getAttribute("cellValue");
            }
            if (clicks == 2) {
                const last_clicked = this.getAttribute("cellValue");

                fetch("main.php?data=" + first_click + last_clicked)
                    .then(response => response.text())
                    .then(d => {
                        console.log(d);
                        try {
                            const data = JSON.parse(d);

                            if (typeof data === "object" && data !== null) {;

                                if (data.type === "move_request") {
                                    const from = data.from;
                                    const to = data.to;

                                    const piece_img = document.querySelector("[cellValue='" + from + "']").innerHTML;
                                    const team_from = document.querySelector("[cellValue='" + from + "']").getAttribute("team");

                                    document.querySelector("[cellValue='" + from + "']").innerHTML = "";
                                    document.querySelector("[cellValue='" + from + "']").setAttribute("team", "free");

                                    document.querySelector("[cellValue='" + to + "']").innerHTML = piece_img;
                                    document.querySelector("[cellValue='" + to + "']").setAttribute("team", team_from);
                                    updateStats();
                                } else if (data.type == "non_valid_request") {
                                    console.log("Kein valider Zug!");
                                } else if(data.type == "log_request") {
                                    console.log(data.output);
                                }
                            }
                        } catch (error) {
                            console.log("Es ist KEIN JSON, sondern ein einfacher String:", d);
                        }
                    });

                clicks = 0;
            }
        });
    });
});

function updateStats() {
    fetch('./stats.json')
        .then((response) => response.json())
        .then((stats) => {
            const points_black = stats.black.points;
            const points_white = stats.white.points;
            document.getElementById("points_black").innerHTML = points_black;
            document.getElementById("points_white").innerHTML = points_white;
        });
}
