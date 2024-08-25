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

  <!-- Slider  -->
   <!-- Slick CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css"/>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css"/>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Slick JS -->
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>

  
  <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
  <script>
    AOS.init();
  </script> -->

  <link rel="stylesheet" href="css/homepage.css">
</head>

<body>
  <?php include "navbar.php" ?>

  <section class="hero">
    <div class="hero-content">
      <h2>Welcome to Our Food Vending Service</h2>
      <p>Delicious and fresh food delivered to your doorstep!</p>
      <a href="food.php" class="btn">Explore Menu</a>
    </div>
    <!-- <img src="images/hero-image.jpg" alt="Hero Image" class="hero-image"> -->
  </section>

  <section id="about" class="about">
    <h2>About Us</h2>
    <p>Welcome to KataKara Food Vending, your go-to destination for delicious and fresh food delivered straight to your
      doorstep! Our mission is to bring you a variety of mouth-watering dishes made from the freshest ingredients,
      ensuring a delightful culinary experience every time you order.

      At KataKara, we believe that great food brings people together. Whether you're craving a hearty meal, a light
      snack, or something in between, our diverse menu has something for everyone. From traditional favorites to
      contemporary delights, our chefs are dedicated to crafting dishes that satisfy your taste buds and keep you coming
      back for more.

      We pride ourselves on our commitment to quality, convenience, and customer satisfaction. Our easy-to-use platform
      makes ordering your favorite meals a breeze, and our prompt delivery service ensures that your food arrives hot
      and fresh.

      Thank you for choosing KataKara. We look forward to serving you and making every meal a memorable one!</p>
  </section>

  <section id="menu" class="menu">
    <h2>Menu</h2>
    <div class="menu-items">
      <div class="menu-item">
        <img src="images/menu-1.jpg" alt="Menu Item 1">
        <h3>Pounded Yam</h3>
        <p>Smooth, fluffy, and perfectly textured, our pounded yam is a traditional favorite that pairs wonderfully with
          a variety of rich and savory soups.</p>
      </div>
      <div class="menu-item">
        <img src="images/menu-2.jpg" alt="Menu Item 2">
        <h3>Jollof Rice</h3>
        <p>Light, fluffy, and cooked to perfection, our rice serves as the ideal base for any meal, soaking up flavors
          and adding a satisfying texture to every bite.</p>
      </div>
      <div class="menu-item">
        <img src="images/menu-3.jpg" alt="Menu Item 3">
        <h3>Fried Chicken</h3>
        <p>Juicy, tender, and seasoned to perfection, our chicken dishes are a crowd-pleaser, offering a burst of flavor
          that complements any side or sauce.</p>
      </div>
    </div>
  </section>

  <section id="specials" class="specials">
    <h2>Specials</h2>
    <p>Highlight ongoing specials, promotions, or discounts.</p>
  </section>

  <section id="testimonials" class="testimonials">
    <h2>Testimonials</h2>
    <div class="testimonial-slider">
        <div class="testimonial">
            <p>"Amazing service! The food is always fresh and delicious."</p>
            <p>- Customer Name</p>
        </div>
        <div class="testimonial">
            <p>"I love the variety on the menu. Something new to try every time."</p>
            <p>- Customer Name</p>
        </div>
        <div class="testimonial">
            <p>"Fast delivery and excellent customer service. Highly recommend!"</p>
            <p>- Customer Name</p>
        </div>
        <div class="testimonial">
            <p>"Fast delivery and excellent customer service. Highly recommend!"</p>
            <p>- Customer Name</p>
        </div>
    </div>
</section>


  <section id="how-it-works" class="how-it-works">
    <h2>How It Works</h2>
    <div class="steps">
      <div class="step">
        <i class="fa fa-search"></i>
        <p>Browse the menu and select your favorite dishes.</p>
      </div>
      <div class="step">
        <i class="fa fa-shopping-cart"></i>
        <p>Place your order and make the payment.</p>
      </div>
      <div class="step">
        <i class="fa fa-truck"></i>
        <p>Receive your order at your doorstep.</p>
      </div>
    </div>
  </section>

  <section id="contact" class="contact">
    <h2>Contact Us</h2>
    <p> Telephone: <a href="tel: +2348103273279 "> +234-810-327-327-9</a>. E-mail Address: <a href="mailto: adebayoabdulrahmon@gmail.com "> adebayoabdulrahmon@gmail.com</a> </p>
  </section>

  <footer>
    <p>&copy; 2023 Your Food Vending Project. All rights reserved.</p>
  </footer>

  <script src="scripts/homepage.js"></script>
  
</body>

</html>