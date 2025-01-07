<?php
ob_start();
session_start();

// include header.php file
include('header.php');
include './database/DBController.php';

?>

<?php

/*  include cart items if it is not empty */
include('Template/_checkout.php');
/*  include cart items if it is not empty */

include('./message.php');

?>

<?php
// include footer.php file
include('footer.php');
?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<style>
    .checkout-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    /* Checkout Section */
    .checkout-section {
        padding: 40px 0;
    }

    .checkout-details {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .cart-items {
        list-style-type: none;
        margin-bottom: 20px;
    }

    .cart-item {
        display: flex;
        align-items: center;
        border-bottom: 1px solid #e0e0e0;
        padding: 15px 0;
    }

    .cart-item:last-child {
        border-bottom: none;
    }

    .cart-item img {
        width: 80px;
        height: 80px;
        border-radius: 8px;
        object-fit: cover;
        margin-right: 20px;
    }

    .item-info {
        flex-grow: 1;
    }

    .item-info h5 {
        font-size: 1.1rem;
        margin-bottom: 5px;
        color: #555;
    }

    .item-info p {
        margin: 5px 0;
        color: #666;
    }

    /* Checkout Form */
    .checkout-form {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 1rem;
        transition: border-color 0.3s;
    }

    .modal-body img {
        border: 2px solid #007bff;
        border-radius: 8px;
    }
    .modal-body p {
        font-size: 1rem;
        color: #333;
    }
</style>