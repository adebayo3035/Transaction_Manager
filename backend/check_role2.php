<?php

function DisplayEditIcon($path, $staff_role, $item_id){
    if($staff_role == "Super Admin"){
        // echo "<td><a href='' data-variable = '$item_id' class='edit_icon'><span class='edit-icon'>&#9998;</span></a></td>";
        // echo "<td><a href='' data-variable = ''$item_id'><span class='delete-icon'>&#128465;</span></a></td>";
        echo "<td><a href=$path?id=" . $item_id."><span class='edit-icon'>&#9998;</span></a></td>";
        
    }
    else if($staff_role == "Admin"){
        echo "";
    }
    else {
        echo "<script> window.location.href='../unauthorized.php'; </script>";
    }
}

function DisplayDeleteIcon($path, $staff_role, $item_id){
    if($staff_role == "Super Admin"){
        echo "<td><a href='$path?id=" . $item_id . "' onclick='return confirmDelete(event, this)'><span class='delete-icon'>&#128465;</span></a></td>";
    } else if($staff_role == "Admin"){
        echo "";
    } else {
        echo "<script> window.location.href='../unauthorized.php'; </script>";
    }
}

function DisplayDeleteFoodIcon($staff_role, $item_id){
    if($staff_role == "Super Admin"){
        // echo "<td><a href='$path?id=" . $item_id . "' onclick='return confirmDelete(event, this)'><span class='delete-icon'>&#128465;</span></a></td>";
        echo "<td><button onclick=\"deleteFood($item_id)\"><span class='delete-icon'>&#128465;</span></button></td>";
    } else if($staff_role == "Admin"){
        echo "";
    } else {
        echo "<script> window.location.href='../unauthorized.php'; </script>";
    }
}
