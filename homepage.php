<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Manager - Homepage</title>
    <!-- font awesome -->
  <script src="https://kit.fontawesome.com/7cab3097e7.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Nunito&family=Roboto&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="css/homepage.css">
</head>
<body>
    <?php include "navbar.php" ?>
      <section class="hero">
        <h2>Welcome to Our Food Vending Service</h2>
        <p>Delicious and fresh food delivered to your doorstep!</p>
        <a href="food.php">Explore Menu</a>
      </section>
    
      <section id="about" class="about">
        <h2>About Us</h2>
        <p>Briefly introduce your food vending business and highlight key features.</p>
        <?php 
        // echo $_SESSION['role']; 
        // echo ($_SESSION['secret_answer']);
        
        ?>
      </section>
    
      <section id="menu" class="menu">
        <h2>Menu</h2>
        <p>Showcase your menu items with images, names, and descriptions.</p>
      </section>
    
      <section id="specials" class="specials">
        <h2>Specials</h2>
        <p>Highlight ongoing specials, promotions, or discounts.</p>
      </section>
    
      <section id="testimonials" class="testimonials">
        <h2>Testimonials</h2>
        <p>Display positive testimonials or reviews from satisfied customers.</p>
      </section>
    
      <section id="how-it-works" class="how-it-works">
        <h2>How It Works</h2>
        <p>Provide a simple step-by-step guide on how customers can place orders.</p>
      </section>
    
      <section id="contact" class="contact">
        <h2>Contact Us</h2>
        <p>Display contact details such as phone number, email, and address.</p>
      </section>
    
      <footer>
        <p>&copy; 2023 Your Food Vending Project. All rights reserved.</p>
      </footer>
    
</body>
</html>