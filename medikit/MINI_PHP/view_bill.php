<?php
session_start();
include("config.php");
include("billing_helpers.php");

if (!isset($_SESSION['patient_id'])) {
  header("Location: loginpatient.php");
  exit;
}

$patient_id = (int)$_SESSION['patient_id'];

try {
  billing_ensure_schema($conn);
} catch (Throwable $e) {
  $schema_error = $e->getMessage();
}

$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

$bill = null;

if (empty($schema_error) && ($bill_id > 0 || $booking_id > 0)) {
  if ($bill_id > 0) {
    $q = "
            SELECT
                cb.id AS bill_id,
                cb.booking_id,
                cb.service_type,
                cb.amount,
                cb.payment_method,
                cb.payment_status,
                cb.created_at,
                vb.appointment_date,
                vb.Note,
                u.firstname AS doc_firstname,
                u.lastname AS doc_lastname,
                u.clinic_name,
                u.address AS clinic_address,
                u.phone_number AS clinic_phone,
                u.email AS clinic_email,
                s.doctor_speciality,
                dat.start_time,
                dat.end_time
            FROM clinic_bills cb
            JOIN visit_booking vb ON vb.id = cb.booking_id
            JOIN users u ON u.id = cb.doctor_id
            LEFT JOIN speciality s ON s.id = vb.speciality_id
            LEFT JOIN doctor_available_time dat ON dat.id = vb.time_id
            WHERE cb.id = ? AND cb.patient_id = ?
            LIMIT 1
        ";

    $stmt = $conn->prepare($q);
    if ($stmt) {
      $stmt->bind_param("ii", $bill_id, $patient_id);
      $stmt->execute();
      $res = $stmt->get_result();
      $bill = $res ? $res->fetch_assoc() : null;
      $stmt->close();
    }
  } else {
    $q = "
            SELECT
                cb.id AS bill_id,
                cb.booking_id,
                cb.service_type,
                cb.amount,
                cb.payment_method,
                cb.payment_status,
                cb.created_at,
                vb.appointment_date,
                vb.Note,
                u.firstname AS doc_firstname,
                u.lastname AS doc_lastname,
                u.clinic_name,
                u.address AS clinic_address,
                u.phone_number AS clinic_phone,
                u.email AS clinic_email,
                s.doctor_speciality,
                dat.start_time,
                dat.end_time
            FROM clinic_bills cb
            JOIN visit_booking vb ON vb.id = cb.booking_id
            JOIN users u ON u.id = cb.doctor_id
            LEFT JOIN speciality s ON s.id = vb.speciality_id
            LEFT JOIN doctor_available_time dat ON dat.id = vb.time_id
            WHERE cb.booking_id = ? AND cb.patient_id = ?
            LIMIT 1
        ";

    $stmt = $conn->prepare($q);
    if ($stmt) {
      $stmt->bind_param("ii", $booking_id, $patient_id);
      $stmt->execute();
      $res = $stmt->get_result();
      $bill = $res ? $res->fetch_assoc() : null;
      $stmt->close();
    }
  }
}

$invoice_no = $bill ? billing_invoice_no((int)$bill['bill_id']) : '';

$pay_status = $bill['payment_status'] ?? '';
$pay_badge = 'bg-warning text-dark';
$pay_text = 'Pending';
if ($pay_status === 'paid') {
  $pay_badge = 'bg-success';
  $pay_text = 'Paid';
}

$appt_date = '-';
$appt_time = '-';
if (!empty($bill['appointment_date'])) {
  $appt_date = date("F j, Y", strtotime((string)$bill['appointment_date']));
}
if (!empty($bill['start_time'])) {
  $appt_time = date("g:i A", strtotime((string)$bill['start_time']));
}

$clinic_name = trim((string)($bill['clinic_name'] ?? ''));
$clinic_address = trim((string)($bill['clinic_address'] ?? ''));
$clinic_phone = trim((string)($bill['clinic_phone'] ?? ''));
$clinic_email = trim((string)($bill['clinic_email'] ?? ''));
?>

<?php include('header.php'); ?>

<style>
  @media print {

    nav.navbar,
    footer,
    .page-header,
    .bill-actions {
      display: none !important;
    }

    body {
      background: #fff !important;
    }

    .card {
      box-shadow: none !important;
      border: 1px solid #e5e7eb !important;
    }
  }
</style>

<main class="container my-5">
  <?php if (!empty($schema_error)): ?>
    <div class="alert alert-danger">Billing table error: <?= htmlspecialchars($schema_error) ?></div>
  <?php elseif (!$bill): ?>
    <div class="card text-center py-5">
      <div class="card-body">
        <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
        <h4 class="fw-bold">Bill Not Found</h4>
        <p class="text-muted">This bill is not available.</p>
        <a href="my_appointments.php" class="btn btn-primary mt-2">Back to My Appointments</a>
      </div>
    </div>
  <?php else: ?>
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="card">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap: 12px;">
              <div>
                <div class="text-muted">Invoice</div>
                <h4 class="fw-bold mb-1">#<?= htmlspecialchars($invoice_no) ?></h4>
                <div class="text-muted small">Payment can be done at clinic or online.</div>
              </div>
              <div class="text-end">
                <span class="badge rounded-pill fs-6 <?= htmlspecialchars($pay_badge) ?>"><?= htmlspecialchars($pay_text) ?></span>
                <div class="text-muted small mt-2">Created: <?= htmlspecialchars(date("F j, Y", strtotime((string)$bill['created_at']))) ?></div>
              </div>
            </div>

            <hr class="my-4">

            <div class="row g-3">
              <div class="col-md-6">
                <div class="fw-bold mb-1">Doctor</div>
                <div>Dr. <?= htmlspecialchars((string)$bill['doc_firstname'] . ' ' . (string)$bill['doc_lastname']) ?></div>
                <div class="text-primary fw-bold"><?= htmlspecialchars((string)($bill['doctor_speciality'] ?? '')) ?></div>
              </div>
              <div class="col-md-6">
                <div class="fw-bold mb-1">Appointment</div>
                <div class="text-muted"><i class="fas fa-calendar-alt me-2"></i><?= htmlspecialchars($appt_date) ?></div>
                <div class="text-muted"><i class="fas fa-clock me-2"></i><?= htmlspecialchars($appt_time) ?></div>
              </div>
            </div>

            <div class="row g-3 mt-2">
              <div class="col-md-12">
                <div class="fw-bold mb-1">Clinic Info</div>
                <div class="text-muted">
                  <?php if ($clinic_name !== ''): ?>
                    <div><strong>Clinic:</strong> <?= htmlspecialchars($clinic_name) ?></div>
                  <?php endif; ?>
                  <?php if ($clinic_address !== ''): ?>
                    <div><strong>Address:</strong> <?= htmlspecialchars($clinic_address) ?></div>
                  <?php endif; ?>
                  <?php if ($clinic_phone !== ''): ?>
                    <div><strong>Phone:</strong> <?= htmlspecialchars($clinic_phone) ?></div>
                  <?php endif; ?>
                  <?php if ($clinic_email !== ''): ?>
                    <div><strong>Email:</strong> <?= htmlspecialchars($clinic_email) ?></div>
                  <?php endif; ?>
                  <?php if ($clinic_name === '' && $clinic_address === '' && $clinic_phone === '' && $clinic_email === ''): ?>
                    <div>-</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <hr class="my-4">

            <div class="table-responsive">
              <table class="table">
                <tbody>
                  <tr>
                    <th style="width: 220px;">Service Type</th>
                    <td><?= htmlspecialchars((string)($bill['service_type'] ?? 'Consultation')) ?></td>
                  </tr>
                  <tr>
                    <th>Amount</th>
                    <td><?= htmlspecialchars(number_format((float)($bill['amount'] ?? 0), 2)) ?></td>
                  </tr>
                  <tr>
                    <th>Payment Method</th>
                    <td><?= htmlspecialchars((string)($bill['payment_method'] ?? '')) ?></td>
                  </tr>
                  <tr>
                    <th>Notes</th>
                    <td><?= htmlspecialchars((string)($bill['Note'] ?? '-')) ?></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3 bill-actions">
              <a href="my_appointments.php" class="btn btn-outline-primary">Back</a>
              <?php if ($pay_status !== 'paid'): ?>
                <button type="button" id="payOnlineBtn" class="btn btn-primary">
                  <i class="fas fa-credit-card me-1"></i> Pay Online
                </button>
              <?php endif; ?>
              <button type="button" class="btn btn-dark" onclick="window.print()"><i class="fas fa-download me-1"></i> Download</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</main>

<?php if ($bill && $pay_status !== 'paid'): ?>
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <script>
    (function() {
      const payBtn = document.getElementById('payOnlineBtn');
      if (!payBtn) return;

      const billId = <?= (int)($bill['bill_id'] ?? 0) ?>;
      const originalHtml = payBtn.innerHTML;

      function setBusy(isBusy) {
        payBtn.disabled = isBusy;
        payBtn.innerHTML = isBusy ? 'Processing…' : originalHtml;
      }

      payBtn.addEventListener('click', async function() {
        try {
          setBusy(true);

          const body = new URLSearchParams({
            bill_id: String(billId)
          });

          const res = await fetch('razorpay_create_order.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body.toString()
          });

          const data = await res.json();
          if (!data || !data.success) {
            alert((data && data.message) ? data.message : 'Failed to start payment.');
            setBusy(false);
            return;
          }

          const options = {
            key: data.key_id,
            amount: data.amount,
            currency: data.currency,
            name: data.name,
            description: data.description,
            order_id: data.order_id,
            prefill: data.prefill || {},
            handler: async function(response) {
              try {
                const verifyBody = new URLSearchParams({
                  bill_id: String(billId),
                  razorpay_order_id: response.razorpay_order_id,
                  razorpay_payment_id: response.razorpay_payment_id,
                  razorpay_signature: response.razorpay_signature
                });

                const vres = await fetch('razorpay_verify_payment.php', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                  },
                  body: verifyBody.toString()
                });

                const vdata = await vres.json();
                if (vdata && vdata.success) {
                  window.location.reload();
                  return;
                }

                alert((vdata && vdata.message) ? vdata.message : 'Payment verification failed.');
                setBusy(false);
              } catch (e) {
                console.error(e);
                alert('Payment verification failed.');
                setBusy(false);
              }
            },
            modal: {
              ondismiss: function() {
                setBusy(false);
              }
            }
          };

          const rzp = new Razorpay(options);
          rzp.on('payment.failed', function(resp) {
            setBusy(false);
            const msg = (resp && resp.error && resp.error.description) ? resp.error.description : 'Payment failed.';
            alert(msg);
          });
          rzp.open();
        } catch (e) {
          console.error(e);
          alert('Failed to start payment.');
          setBusy(false);
        }
      });
    })();
  </script>
<?php endif; ?>

<?php include('footer.php'); ?>