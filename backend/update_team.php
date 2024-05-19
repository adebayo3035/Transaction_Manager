<?php
if (isset($_POST['btnUpdateTeam'])) {
    include_once "config.php";
    session_start();
    $team_name = mysqli_real_escape_string($conn, $_POST['team_name']);
    $current_team = mysqli_real_escape_string($conn, $_POST['team_name_hidden']);
    $group_id = mysqli_real_escape_string($conn, $_POST['group_id']);
    $team_id = mysqli_real_escape_string($conn, $_POST['team_id']);
    if (!empty($team_name) && !empty($group_id) && !empty($team_id)) {
        if ($team_name != $current_team) {
            // Prepare and execute a query to check if the email or phone number already exists
            $sql_team = mysqli_query($conn, "SELECT team_name, group_id FROM team WHERE team_name = '{$team_name}'");
            if (mysqli_num_rows($sql_team) > 0) {
                //echo $team_name . " and ". $group_id;
                echo "<script>alert('$team_name already exists. Please use a different team Name.');window.location.href='../edit_team.php?id=" . $team_id . "';</script>";
                exit();
            } else {
                updateTeam($conn, $team_id, $team_name, $group_id);
            }
        }
        else{
            updateTeam($conn, $team_id, $team_name, $group_id);
        }


    } else {
        echo "<script>alert(' All Input fields are required.'); </script>";
    }

}

function updateTeam($conn, $team_id, $team_name, $group_id)
{
    $updateteamQuery = "UPDATE team SET team_name = '{$team_name}', group_id = '{$group_id}' WHERE team_id = '{$team_id}'";
    $updateteamResult = mysqli_query($conn, $updateteamQuery);

    if (!$updateteamResult) {
        // If there's an error updating the team, display an error message and redirect back to the edit page
        echo "<script>alert('Error Updating Team and Group Name: " . mysqli_error($conn) . "'); window.location.href='../edit_team.php?id={$team_id}'; </script>";
    } else {
        // If the update is successful, display a success message and redirect to the teams page
        echo "<script>alert('Team Information has been successfully Updated.'); window.location.href='../teams.php'; </script>";
    }
}
