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
        echo "<td><a href=$path?id=" . $item_id."><span class='delete-icon'>&#128465;</span></a></td>";
    }
    else if($staff_role == "Admin"){
        echo "";
    }
    else {
        echo "<script> window.location.href='../unauthorized.php'; </script>";
    }
}


 // echo "<script>
    //         document.addEventListener('DOMContentLoaded', function() {
    //             var editIcons = document.querySelectorAll('.edit_icon');
    //             editIcons.forEach(function(icon) {
    //                 icon.addEventListener('click', function() {
    //                     var variable = this.getAttribute('data-variable');
    //                     // Call function to display modal with appropriate data based on variable
    //                     toggleModal2(variable);
    //                 });
    //             });
    //         });
    //         </script>";