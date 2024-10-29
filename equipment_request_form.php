<?php
session_start();
include 'db_connect.php';

$assignmentId = $_GET['assignment_id'] ?? null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nameEquipmentRequest = $_POST['NameEquipmentRequest'];
    $description = $_POST['Description'];
    $equipmentIds = $_POST['Equipment_id'];
    $quantitiesUsed = $_POST['QuantityUsed'];

    foreach ($equipmentIds as $index => $equipmentId) {
        $quantityUsed = $quantitiesUsed[$index];

        $checkQuantityQuery = "SELECT QuantityAvailable FROM equipment WHERE id = ?";
        $checkQuantityStmt = $conn->prepare($checkQuantityQuery);
        $checkQuantityStmt->bind_param("i", $equipmentId);
        $checkQuantityStmt->execute();
        $result = $checkQuantityStmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['QuantityAvailable'] < $quantityUsed) {
                $_SESSION['error'] = "จำนวนอุปกรณ์ที่เบิกเกินจำนวนที่มีอยู่: $quantityUsed / " . $row['QuantityAvailable'];
                header("Location: equipment_request_form.php?assignment_id=$assignmentId"); // กลับไปยังหน้าเดิมพร้อม assignment_id
                exit();
            }
        } else {
            $_SESSION['error'] = "ไม่พบอุปกรณ์ที่เลือก";
            header("Location: equipment_request_form.php?assignment_id=$assignmentId"); // กลับไปยังหน้าเดิมพร้อม assignment_id
            exit();
        }
    }


    $insertQuery = "INSERT INTO equipmentrequest (NameEquipmentRequest, Description, Assignment_id) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("ssi", $nameEquipmentRequest, $description, $assignmentId);
    $stmt->execute();


    if ($stmt->affected_rows > 0) {
        $equipmentRequestId = $stmt->insert_id;


        foreach ($equipmentIds as $index => $equipmentId) {
            $quantityUsed = $quantitiesUsed[$index];

            $issueQuery = "INSERT INTO issuerequisition (EquipmentRequest_id, Equipment_id, QuantityUsed) VALUES (?, ?, ?)";
            $issueStmt = $conn->prepare($issueQuery);
            $issueStmt->bind_param("iii", $equipmentRequestId, $equipmentId, $quantityUsed);
            $issueStmt->execute();


            $updateEquipmentQuery = "UPDATE equipment SET QuantityAvailable = QuantityAvailable - ? WHERE id = ?";
            $updateEquipmentStmt = $conn->prepare($updateEquipmentQuery);
            $updateEquipmentStmt->bind_param("ii", $quantityUsed, $equipmentId);
            $updateEquipmentStmt->execute();
        }




        $updateAssignmentStatusQuery = "UPDATE assignment SET Status = 'เสร็จสิ้น', ScheduledDate = NOW() WHERE id = ?";
        $updateAssignmentStatusStmt = $conn->prepare($updateAssignmentStatusQuery);
        $updateAssignmentStatusStmt->bind_param("i", $assignmentId);
        $updateAssignmentStatusStmt->execute();




        $getRepairRequestIdQuery = "SELECT RepairRequest_id FROM assignment WHERE id = ?";
        $getRepairRequestIdStmt = $conn->prepare($getRepairRequestIdQuery);
        $getRepairRequestIdStmt->bind_param("i", $assignmentId);
        $getRepairRequestIdStmt->execute();
        $result = $getRepairRequestIdStmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $repairRequestId = $row['RepairRequest_id'];


            $updateRepairRequestStatusQuery = "UPDATE repairrequest SET Status = 'เสร็จสิ้น' WHERE id = ?";
            $updateRepairRequestStatusStmt = $conn->prepare($updateRepairRequestStatusQuery);
            $updateRepairRequestStatusStmt->bind_param("i", $repairRequestId);
            $updateRepairRequestStatusStmt->execute();
        } else {
            echo "ไม่พบ RepairRequest_id สำหรับ Assignment นี้";
            exit();
        }

        header("Location: view_repair_status.php");
        exit();
    } else {

        header("Location: equipmennt_request_form.php");
        exit();
    }
}

// Fetch equipment data
$equipmentQuery = "SELECT * FROM equipment WHERE QuantityAvailable > 0";
$equipmentResult = $conn->query($equipmentQuery);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Equipment Request</title>
    <link rel="stylesheet" href="styles.css">
    <script>
       function addEquipmentField() {
    const equipmentContainer = document.getElementById('equipment-container');
    const newField = document.createElement('div');
    newField.className = 'equipment-item'; // เพิ่มคลาสให้เหมือนกับแถวเดิม
    newField.innerHTML = `
        <label class="eqreq_label">อุปกรณ์ที่ต้องการเบิก:</label>
        <select name="Equipment_id[]" class="eqreq_select" required>
            <?php
            $equipmentResult->data_seek(0);
            while ($equipment = $equipmentResult->fetch_assoc()): ?>
                <option value="<?php echo $equipment['id']; ?>"><?php echo $equipment['Name']; ?> (คงเหลือ: <?php echo $equipment['QuantityAvailable']; ?>)</option>
            <?php endwhile; ?>
        </select>

        <label class="eqreq_label">จำนวนที่ต้องการเบิก:</label>
        <input type="number" name="QuantityUsed[]" min="1" class="eqreq_input-number" required>

        <button type="button" onclick="removeEquipmentField(this)" class="btn btn-danger eqreq_btn-remove">ลบ</button>
    `;
    equipmentContainer.appendChild(newField);
}


        function removeEquipmentField(button) {
            const equipmentContainer = document.getElementById('equipment-container');
            equipmentContainer.removeChild(button.parentNode);
        }
    </script>
</head>

<body>
<?php
    if (isset($_SESSION['error'])) {
        echo "<script>alert('{$_SESSION['error']}');</script>";
        unset($_SESSION['error']); // ลบข้อความแจ้งเตือนหลังแสดงแล้ว
    }
    ?>
    <h2 class="eqreq_heading">แบบฟอร์มเบิกอุปกรณ์</h2>
    <form method="post" class="eqreq_form">
        <label for="NameEquipmentRequest" class="eqreq_label">ชื่องานที่ซ่อม:</label>
        <input type="text" name="NameEquipmentRequest" class="eqreq_input" required>

        <label for="Description" class="eqreq_label">รายละเอียดงานซ่อม:</label>
        <textarea name="Description" class="eqreq_textarea" required></textarea>

        <div id="equipment-container" class="eqreq_equipment-container">
            <div class="equipment-item">
                <label class="eqreq_label">อุปกรณ์ที่ต้องการเบิก:</label>
                <select name="Equipment_id[]" class="eqreq_select" required>
                    <?php
                    $equipmentResult->data_seek(0);
                    while ($equipment = $equipmentResult->fetch_assoc()): ?>
                        <option value="<?php echo $equipment['id']; ?>"><?php echo $equipment['Name']; ?> (คงเหลือ: <?php echo $equipment['QuantityAvailable']; ?>)</option>
                    <?php endwhile; ?>
                </select>

                <label class="eqreq_label">จำนวนที่ต้องการเบิก:</label>
                <input type="number" name="QuantityUsed[]" min="1" class="eqreq_input-number" required>
            </div>
        </div>

        <button type="button" onclick="addEquipmentField()" class="btn btn-secondary eqreq_btn-add">เพิ่มอุปกรณ์</button>
        <button type="submit" class="btn btn-primary eqreq_btn-submit">บันทึกการเบิก</button>
    </form>

</body>

</html>