<?php
include '../../database/DBController.php';

header('Content-Type: application/json'); // Thêm header này để đảm bảo trả về JSON

if(isset($_GET['apartment_id'])) {
    $apartment_id = mysqli_real_escape_string($conn, $_GET['apartment_id']);
    
    $query = "SELECT DISTINCT r.ID, r.NationalId, u.UserName
            FROM resident r 
            INNER JOIN users u ON r.ID = u.ResidentID 
            LEFT JOIN ResidentApartment ra ON r.ID = ra.ResidentId
            WHERE r.NationalId IS NOT NULL 
            AND r.ID NOT IN (
                SELECT ResidentId 
                FROM ResidentApartment 
                WHERE ApartmentId = '$apartment_id'
            )
            ORDER BY u.UserName";
    
    $result = mysqli_query($conn, $query);
    $residents = array();
    while($row = mysqli_fetch_assoc($result)) {
        $residents[] = $row;
    }
    
    echo json_encode($residents);
}
?>