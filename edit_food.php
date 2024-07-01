<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Food Information</title>
    <link rel="stylesheet" href="css/add_food.css">

</head>

<body>
    <?php
    include "navbar.php";
    $id = $_GET['id'];
    $query = mysqli_query($conn, "select * from `food` where food_id='$id'");
    $row = mysqli_fetch_array($query);
    ?>

    <div class="container">
        <h1>Edit Food Item</h1>
        <form id="Updatefood-form">
            <input type="text" id="food_id" name="food_id" value="<?php echo htmlspecialchars($row['food_id']); ?>" hidden>
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($row['food_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" value='<?php echo $row['food_description'] ?>'
                    required><?php echo htmlspecialchars($row['food_description']); ?></textarea>
            </div>
            <div class="form-group">
                <label for="price">Price:</label>
                <input type="number" id="price" name="price" step="0.01" value="<?php echo htmlspecialchars($row['food_price']); ?>"
                    required>
            </div>
            <div class="form-group">
                <label for="quantity">Quantity:</label>
                <input type="number" id="quantity2" name="quantity2" value="<?php echo htmlspecialchars($row['available_quantity']); ?>"
                hidden>
                <input type="number" id="quantity" name="quantity" value="<?php echo htmlspecialchars($row['available_quantity']); ?>"
                    required>
            </div>
            <div class="form-group">
                <label for="available">Available:</label>
                <select id="available" name="available">
                    <option value="1" <?php echo $row['availability_status'] == 1 ? 'selected' : ''; ?>>Yes</option>
                    <option value="0" <?php echo $row['availability_status'] == 0 ? 'selected' : ''; ?>>No</option>
                </select>
                </select>
            </div>
            <button type="submit">Update Food Item</button>
        </form>
        <div id="message"></div>
    </div>
    <script src="scripts/edit_food.js"></script>

</body>

</html>