<?php
$page = 'daily-report';
include 'includes/header.php';
?>
  <!-- DataTables -->
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  
</head>
<body class="hold-transition sidebar-mini layout-footer-fixed layout-navbar-fixed layout-fixed dark-mode sidebar-collapse">
<div class="wrapper">
  <?php 
    include 'includes/topbar.php';
    include 'includes/sidebar.php';
  ?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Daily Report</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Daily Report</li>
            </ol>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
    <!-- Main content -->
    <section class="content">
     <div class="container-fluid">
     <div class="card">
              <div class="card-header pb-0">
                <form action="#">
                  <div class="reportDate mt-3">
                    <div class="row">
                      <div class="col-md-2">
                        <div class="form-group">
                          <input type="date" class="form-control" id="reportdate" name="reportdate" placeholder=" " value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                          <label for="reportdate" class="form-label">Report Date <span style="color:red;">*</span></label>
                        </div>
                      </div>
                      <div class="col-md-2">
                        <div class="form-group">
                          <input type="button" class="btn btn-primary" value="Search">
                        </div>
                      </div>
                    </div>
                  </div>
                </form>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <table id="user-table" class="table table-bordered table-striped" width="100%">
                  <thead>
                    <tr>
                      <th class="align-items-center text-center">Emp. ID</th>
                      <th class="align-items-center text-left">Employee Name</th>
                      <th class="align-items-center text-center">Designation</th>
                      <th class="align-items-center text-center">Branch</th>
                      <th class="align-items-center text-center">Sch. In</th>
                      <th class="align-items-center text-center">Sch. Out</th>
                      <th class="align-items-center text-center">Wrkng hrs.</th>
                      <th class="align-items-center text-center">In Time</th>
                      <th class="align-items-center text-center">Out Time</th>
                      <th class="align-items-center text-center">Over Time</th>
                      <th class="align-items-center text-center">Late In</th>
                      <th class="align-items-center text-center">Late Out</th>
                      <th class="align-items-center text-center">Marked As</th>
                      <th class="align-items-center text-center">Type</th>
                      <th class="align-items-center text-center">Remarks</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td class="align-items-center text-center">0101</td>
                      <td class="align-items-center text-left"><b>Mr. Sagar Khatiwada</b></td>
                      <td class="align-items-center text-left">MIS Manager</td>
                      <td class="align-items-center text-center">Head Office</td>
                      <td class="align-items-center text-center">09:30</td>
                      <td class="align-items-center text-center">18:00</td>
                      <td class="align-items-center text-center">08:30</td>
                      <td class="align-items-center text-center">10:05</td>
                      <td class="align-items-center text-center">-</td>
                      <td class="align-items-center text-center">05:25</td> 
                      <td class="align-items-center text-center">00:23</td>
                      <td class="align-items-center text-center">00:05</td>
                      <td class="align-items-center text-center">Present</td>
                      <td class="align-items-center text-center">Manual</td>
                      <td class="align-items-center text-center">Punch Missed</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- /.card-body -->
            </div>
            <!-- /.card -->
          </div>
          <!-- /.col -->
        </div>
        <!-- /.row -->
     </div><!-- /.container-fluid -->
    </section><!-- /.content -->
  </div><!-- /.content-wrapper -->

  <?php 
  include 'includes/footer.php';
  ?>

</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="<?php echo $home;?>plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="<?php echo $home;?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables  & Plugins -->
<script src="<?php echo $home;?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo $home;?>plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $home;?>plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $home;?>plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $home;?>plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<!-- AdminLTE -->
<script src="<?php echo $home;?>dist/js/adminlte.js"></script>

<!-- Page Specific Scripts -->
<script>
  $(function () {
    $("#user-table").DataTable({
      "responsive": true,
      "lengthChange": false,
      "autoWidth": true,
      "paging": false,
      "searching": true,
      "ordering": false,
      "info": false,
      "pageLength": 30, // Set the default number of rows to display
      "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ], // Define the options in the page length dropdown menu
      "pagingType": "full_numbers", // Controls the pagination controls' appearance (options: 'simple', 'simple_numbers', 'full', 'full_numbers', 'first_last_numbers')
      "buttons": [
        'colvis', // Add column visibility button
      {
        extend: 'print',
        text: 'Print',
        autoPrint: true,
        customize: function (win) {
          $(win.document.body)
            .css('font-size', '10pt')
            .prepend(
              '<img src="<?php echo $home;?>resources/logo.png" style="position:absolute; top:0; left:0;" />'
            );

          $(win.document.body).find('table')
            .addClass('compact')
            .css('font-size', 'inherit');

          // Set paper size and orientation
          var css = '@page { size: A4 landscape; }';
          var head = win.document.head || win.document.getElementsByTagName('head')[0];
          var style = win.document.createElement('style');
          style.type = 'text/css';
          style.media = 'print';
          if (style.styleSheet) {
            style.styleSheet.cssText = css;
          } else {
            style.appendChild(win.document.createTextNode(css));
          }
          head.appendChild(style);
        }
      },
      'pdf'
    ], //copy, csv, excel, pdf, print, colvis
      "language": {
        "paginate": {
          "first": '<i class="fas fa-angle-double-left"></i>',
          "previous": '<i class="fas fa-angle-left"></i>',
          "next": '<i class="fas fa-angle-right"></i>',
          "last": '<i class="fas fa-angle-double-right"></i>'
        },
        "emptyTable": "No data available in table",
        "info": "Showing _START_ to _END_ of _TOTAL_ entries",
        "infoEmpty": "Showing 0 to 0 of 0 entries",
        "infoFiltered": "(filtered from _MAX_ total entries)",
        "lengthMenu": "Show _MENU_ entries",
        "loadingRecords": "Loading...",
        "processing": "Processing...",
        "search": "Search:",
        "zeroRecords": "No matching records found"
      }
    }).buttons().container().appendTo('#user-table_wrapper .col-md-6:eq(0)');

    // Custom button action
    $('#custom-filter-btn').on('click', function() {
      $('#addUserModal').modal({
        backdrop: 'static',
        keyboard: false
      });
    });
  });
</script>
<!-- AdminLTE for demo purposes -->
<script src="<?php echo $home;?>dist/js/demo.js"></script>

</body>
</html>