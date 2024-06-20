var modal = document.getElementById("addGroupModal");
var userRole = "<?php echo $_SESSION['role']; ?>";
function toggleModal(userRole, modal) {
    if (userRole === "Super Admin") {
      modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
    }
    else {
      // Handle any other roles or unauthenticated users
      window.location.href = 'unauthorized.php';
    }
  }
  