<?php
include '../../database/DBController.php';

header('Content-Type: application/json');

if(isset($_GET['resident_id'])) {
    $resident_id = mysqli_real_escape_string($conn, $_GET['resident_id']);
    
    $query = "SELECT r.ID, r.NationalId, r.Dob, r.Gender,
                     u.UserName, u.Email, u.PhoneNumber
            FROM resident r
            INNER JOIN users u ON r.ID = u.ResidentID
            WHERE r.ID = '$resident_id'";
    
    $result = mysqli_query($conn, $query);
    if($row = mysqli_fetch_assoc($result)) {
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Không tìm thấy thông tin']);
    }
}
?> 