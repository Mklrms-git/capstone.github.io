<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'mhavis');

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
$result = $conn->query("SELECT last_name FROM users WHERE username = '$username' LIMIT 1");
$last_name = ($result && $row = $result->fetch_assoc()) ? $row['last_name'] : $_SESSION['username'];

// Add new patient
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_patient'])) {
    $stmt = $conn->prepare("INSERT INTO patients (first_name, last_name, age, sex, address, birthday, contact_no, patient_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisssss", $_POST['first_name'], $_POST['last_name'], $_POST['age'], $_POST['sex'], $_POST['address'], $_POST['birthday'], $_POST['contact_no'], $_POST['patient_type']);
    $stmt->execute();
    $stmt->close();
    header("Location: patient_record.php");
    exit();
}

// Edit patient
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_patient'])) {
    $stmt = $conn->prepare("UPDATE patients SET first_name=?, last_name=?, age=?, sex=?, address=?, birthday=?, contact_no=?, patient_type=? WHERE patient_id=?");
    $stmt->bind_param("ssisssssi", $_POST['first_name'], $_POST['last_name'], $_POST['age'], $_POST['sex'], $_POST['address'], $_POST['birthday'], $_POST['contact_no'], $_POST['patient_type'], $_POST['patient_id']);
    $stmt->execute();
    $stmt->close();
    header("Location: patient_record.php");
    exit();
}

// Delete patient
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    // Use a transaction to ensure all-or-nothing deletion
    $conn->begin_transaction();
    try {
        // Collect appointment IDs for this patient to delete dependent transactions
        $appointmentIds = [];
        if ($stmt = $conn->prepare("SELECT id FROM appointments WHERE patient_id = ?")) {
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $appointmentIds[] = (int)$row['id'];
            }
            $stmt->close();
        }

        // Delete transactions linked to appointments
        if (!empty($appointmentIds)) {
            $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
            $types = str_repeat('i', count($appointmentIds));
            $sql = "DELETE FROM transactions WHERE appointment_id IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$appointmentIds);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Delete appointments for this patient
        if ($stmt = $conn->prepare("DELETE FROM appointments WHERE patient_id = ?")) {
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();
            $stmt->close();
        }

        // Delete transactions directly linked to patient_id (if any)
        if ($stmt = $conn->prepare("DELETE FROM transactions WHERE patient_id = ?")) {
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();
            $stmt->close();
        }

        // Delete prescriptions
        if ($stmt = $conn->prepare("DELETE FROM prescriptions WHERE patient_id = ?")) {
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();
            $stmt->close();
        }

        // Ensure patient user account (registered email) is removed
        if ($stmt = $conn->prepare("DELETE FROM patient_users WHERE patient_id = ?")) {
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();
            $stmt->close();
        }

        // Finally, delete the patient record
        $stmt = $conn->prepare("DELETE FROM patients WHERE patient_id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            $conn->commit();
        } else {
            $conn->rollback();
        }
    } catch (Throwable $e) {
        $conn->rollback();
    }

    header("Location: patient_record.php");
    exit();
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$total = $conn->query("SELECT COUNT(*) as total FROM patients")->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

$patients = $conn->query("SELECT * FROM patients ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Patient Records</title>
  <link rel="shortcut icon" href="img/logo2.jpeg" type="image/x-icon" />
  <style>
    body { margin: 0; font-family: 'Segoe UI', sans-serif; }
    .header, .sidebar, .main, table, form, .modal { all: revert; }
    .header { background: linear-gradient(to right, #1218a5, #fff); padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
    .sidebar { width: 220px; background: linear-gradient(to bottom, #1218a5, #fff); height: 100vh; padding: 20px 0; }
    .sidebar a { display: block; padding: 15px 25px; text-decoration: none; color: black; }
    .sidebar a:hover, .sidebar .active { background-color: white; color: #2a42c4; font-weight: bold; }
    .main { padding: 40px; flex-grow: 1; }
    .container { display: flex; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    form input, form select { margin: 5px; padding: 10px; width: 200px; }
    form button { padding: 10px 20px; background: #1218a5; color: #fff; border: none; cursor: pointer; }
    .pagination { margin-top: 20px; text-align: center; }
    .pagination a { margin: 0 5px; text-decoration: none; color: #1218a5; }
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
    .modal-content { background: #fff; padding: 20px; border-radius: 10px; width: 500px; }
    .close-btn { float: right; cursor: pointer; font-weight: bold; color: red; }
  </style>
  <script>
    function openEditModal(data) {
      document.getElementById('editModal').style.display = 'flex';
      for (const key in data) {
        const field = document.querySelector(`#editModal [name="${key}"]`);
        if (field) field.value = data[key];
      }
    }

    function closeModal() {
      document.getElementById('editModal').style.display = 'none';
    }

    function confirmDelete(id) {
      confirmDialog("Are you sure you want to delete this patient?", "Delete", "Cancel").then(function(confirmed) {
        if (confirmed) {
          window.location = `?delete_id=${id}`;
        }
      });
    }
  </script>
</head>
<body>
  <div class="header">
    <img src="img/logo.png" alt="Logo" height="60">
    <h1>Mhavis Medical & Diagnostic Center</h1>
    <a class="logout" href="logout.php">Logout</a>
  </div>

  <div class="container">
    <div class="sidebar">
      <a href="admin_dashboard.php">Dashboard</a>
      <a class="active" href="#">Patient Records</a>
      <a href="doctor_management.php">Doctor Management</a>
      <a href="appointment.php">Appointments</a>
      <a href="medical_record.php">Medical Records</a>
      <a href="notification.php">Notification</a>
      <a href="setting.php">Settings</a>
      <a href="report_analytics.php">Report Analytics</a>
    </div>

    <div class="main">
      <h2>Patient Records</h2>
      <form method="post">
        <input type="hidden" name="add_patient" value="1">
        <input name="first_name" placeholder="First Name" required>
        <input name="last_name" placeholder="Last Name" required>
        <input name="age" type="number" placeholder="Age" required>
        <select name="sex" required>
          <option value="">Select Sex</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
        </select>
        <input name="address" placeholder="Address" required>
        <input name="birthday" type="date" required>
        <input name="contact_no" placeholder="Contact No." required>
        <select name="patient_type" required>
          <option value="">Patient Type</option>
          <option value="New">New</option>
          <option value="Old">Old</option>
        </select>
        <button type="submit">Add Patient</button>
      </form>

      <table>
        <thead>
          <tr>
            <th>ID</th><th>First</th><th>Last</th><th>Age</th><th>Sex</th><th>Address</th><th>Birthday</th><th>Contact</th><th>Type</th><th>Timestamp</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $patients->fetch_assoc()): ?>
            <tr>
              <td><?= $row['patient_id'] ?></td>
              <td><?= htmlspecialchars($row['first_name']) ?></td>
              <td><?= htmlspecialchars($row['last_name']) ?></td>
              <td><?= $row['age'] ?></td>
              <td><?= $row['sex'] ?></td>
              <td><?= htmlspecialchars($row['address']) ?></td>
              <td><?= $row['birthday'] ?></td>
              <td><?= htmlspecialchars($row['contact_no']) ?></td>
              <td><?= $row['patient_type'] ?></td>
              <td><?= $row['created_at'] ?></td>
              <td>
                <button onclick='openEditModal(<?= json_encode($row) ?>)'>Edit</button>
                <button onclick='confirmDelete(<?= $row['patient_id'] ?>)'>Delete</button>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

      <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <a href="?page=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div id="editModal" class="modal" onclick="closeModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
      <span class="close-btn" onclick="closeModal()">X</span>
      <h3>Edit Patient</h3>
      <form method="post">
        <input type="hidden" name="edit_patient" value="1">
        <input type="hidden" name="patient_id">
        <input name="first_name" placeholder="First Name" required>
        <input name="last_name" placeholder="Last Name" required>
        <input name="age" type="number" placeholder="Age" required>
        <select name="sex" required>
          <option value="">Select Sex</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
        </select>
        <input name="address" placeholder="Address" required>
        <input name="birthday" type="date" required>
        <input name="contact_no" placeholder="Contact No." required>
        <select name="patient_type" required>
          <option value="New">New</option>
          <option value="Old">Old</option>
        </select>
        <button type="submit">Update Patient</button>
      </form>
    </div>
  </div>
</body>
</html>
