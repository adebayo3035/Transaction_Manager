<?php
          $staff_role = $_SESSION['role'];
          if($staff_role == "Super Admin"){
            echo '<th colspan="2">Action</th>';
          }
          else{
            echo '';
          }
          