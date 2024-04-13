<?php
function DisplayTable($staff_role, $item_id){
    if($staff_role == "Super Admin"){
        echo "<td><a href='edit.php?id=" . $item_id. "'><span class='edit-icon'>&#9998;</span></a></td>";
        echo "<td><a href='delete.php?id=" . $item_id. "'><span class='delete-icon'>&#128465;</span></a></td>";
    }
    else if($staff_role == "Admin"){
        echo "";
    }
    else {
        echo "<script> window.location.href='../unauthorized.php'; </script>";
    }
}