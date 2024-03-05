 <?php 
  session_start();
  include_once "backend/config.php";
  if(!isset($_SESSION['unique_id'])){
    header("location: index.php");
  }
?>