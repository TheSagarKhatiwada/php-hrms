<?php
$page = 'monthly-report';
include 'includes/header.php';
?>
  <!-- DataTables -->
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

  <!-- Tempusdominus Bootstrap 4 -->
<link rel="stylesheet" href="<?php echo $home;?>plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
<!-- daterangepicker -->
<link rel="stylesheet" href="<?php echo $home;?>plugins/daterangepicker/daterangepicker.css">
  
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
            <h1 class="m-0">Monthly Report</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Monthly Report</li>
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
        <div class="card-header pb-3 ">
          <div class="row">
            <div class="col-md-2">
              <div class="monthDate">
                <form action="#">
                  <div class="form-group">
                    <div class="input-group date" id="attendanceMonth" data-target-input="nearest">
                      <input type="text" class="form-control datetimepicker-input" data-target="#attendanceMonth"/>
                      <label for="attendanceMonth" class="form-label">Select Month <span style="color:red;">*</span></label>
                      <div class="input-group-append" data-target="#attendanceMonth" data-toggle="datetimepicker">
                        <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                      </div>
                    </div>
                  </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <input type="button" class="btn btn-primary" value="Search">
              </div>
            </div>
                </form>
            <div class="col-md-2 text-center mt-2">OR</div>
            <div class="col-md-2"></div>
            <div class="col-md-3 text-right">
              <div class="row">
                <div class="col-md-10">  
                  <div class="form-group">
                    <div class="input-group" id="reportDateID">
                      <input type="text" class="form-control float-right" id="reportDateRange">
                      <label for="reportDateRange" class="form-label">Select Date Range <span style="color:red;">*</span></label>
                      <div class="input-group-append">
                        <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-2 text-left">
                  <div class="form-group">
                    <input type="button" class="btn btn-primary" value="Search">
                  </div>
                </div>
                  </div>
                </div>
                </div>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <table id="user-table" class="table table-sm table-bordered table-striped" width="100%">
                  <thead>
                    <tr>
                      <th class="align-items-center text-center" style="font-size: 1.8rem;" colspan="16">Company Name</th>
                    </tr>
                    <tr>
                    <th class="align-items-center text-center" colspan="16">Monthly Attendance Report of Jan 2025</th>
                    </tr>
                    <tr>
                    <th class="align-items-center text-center" colspan="3">Emp. ID: 0101</th>
                    <th class="align-items-center text-center" colspan="2">Report Date: Jan 2025</th>
                    <th class="align-items-center text-center" colspan="3">Branch: Main Branch</th>
                    <th class="align-items-center text-right" colspan="8">Employee Name: Mr. Sagar Khatiwada</th>
                    </tr>
                    <tr>
                      <th class="align-items-center text-center">SN</th>
                      <th class="align-items-center text-center">Date</th>
                      <th class="align-items-center text-center">Sch. In</th>
                      <th class="align-items-center text-center">Sch. Out</th>
                      <th class="align-items-center text-center">Wrkng hrs.</th>
                      <th class="align-items-center text-center">In Time</th>
                      <th class="align-items-center text-center">Out Time</th>
                      <th class="align-items-center text-center">Actual</th>
                      <th class="align-items-center text-center">OT</th>
                      <th class="align-items-center text-center">Late In</th>
                      <th class="align-items-center text-center">Early Out</th>
                      <th class="align-items-center text-center">Early In</th>
                      <th class="align-items-center text-center">Late Out</th>
                      <th class="align-items-center text-center">Marked As</th>
                      <th class="align-items-center text-center">Type</th>
                      <th class="align-items-center text-center">Remarks</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td class="align-items-center text-center">01</td>
                      <td class="align-items-center text-center">23-10-2024</td>
                      <td class="align-items-center text-center">09:30</td>
                      <td class="align-items-center text-center">18:00</td>
                      <td class="align-items-center text-center">08:30</td>
                      <td class="align-items-center text-center">10:05</td>
                      <td class="align-items-center text-center">-</td>
                      <td class="align-items-center text-center">08:48</td> 
                      <td class="align-items-center text-center">05:25</td> 
                      <td class="align-items-center text-center">00:23</td>
                      <td class="align-items-center text-center">00:05</td>
                      <td class="align-items-center text-center">00:05</td>
                      <td class="align-items-center text-center">00:05</td>
                      <td class="align-items-center text-center">Present</td>
                      <td class="align-items-center text-center">Manual</td>
                      <td class="align-items-center text-center">Out Punch Missed</td>
                    </tr>
                    <tr>
                      <th class="align-items-center text-center" colspan="3">Total</th>
                      <th class="align-items-center text-center"></th>
                      <th class="align-items-center text-center">288:52</th>
                      <th class="align-items-center text-center"></th>
                      <th class="align-items-center text-center"></th>
                      <th class="align-items-center text-center">290:30</th>
                      <th class="align-items-center text-center">10:15</th>
                      <th class="align-items-center text-center">01:03</th>
                      <th class="align-items-center text-center">0:00</th>
                      <th class="align-items-center text-center">0:00</th>
                      <th class="align-items-center text-center">05:03</th>
                      <th class="align-items-center text-center"></th>
                      <th class="align-items-center text-center"></th>
                      <th class="align-items-center text-center"></th>
                    </tr>
                  </tbody>
                  <tfoot>
                    <th class="align-items-center text-right" colspan="2">Sagar's Summary</th>
                    <th class="align-items-center text-center" colspan="1">Present: 5</th>
                    <th class="align-items-center text-center" colspan="1">Absent: 3</th>
                    <th class="align-items-center text-center" colspan="1">Weekend: 4</th>
                    <th class="align-items-center text-center" colspan="2">Holiday: 2</th>
                    <th class="align-items-center text-center" colspan="2">Paid Leave: 1</th>
                    <th class="align-items-center text-center" colspan="2">Unpaid Leave: 2</th>
                    <th class="align-items-center text-center" colspan="2">Missed: 5</th>
                    <th class="align-items-center text-center" colspan="2">Manual: 0</th>
                    <th class="align-items-center text-center" colspan="1">Misc: 2</th>
                  </tfoot>
                </table>
              </div>
              <!-- /.card-body -->
            </div>
          </div>
        </div>

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
<!-- Tempusdominus Bootstrap 4 -->
<script src="<?php echo $home;?>plugins/moment/moment.min.js"></script>
<script src="<?php echo $home;?>plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<!-- daterangepicker -->
<script src="<?php echo $home;?>plugins/moment/moment.min.js"></script>
<script src="<?php echo $home;?>plugins/daterangepicker/daterangepicker.js"></script>

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

    // Initialize the month picker
    $('#attendanceMonth').datetimepicker({
      format: 'MM/YYYY',
      viewMode: 'months',
      minViewMode: 'months'
    });

    // Initialize the date range picker
    $('#reportDateRange').daterangepicker({
      locale: {
        format: 'DD/MM/YYYY'
      },
      opens: 'left', // The picker will open to the left of the input field
      // showDropdowns: true, // Adds dropdowns for selecting month and year
      alwaysShowCalendars: true, // Keeps the calendars visible at all times
      startDate: moment().subtract(1, 'months').startOf('month'), // First day of the previous month
      endDate: moment().endOf('month'), // Last day of the current month
      maxDate: moment(), // Prevents selection of future dates
    
    });
  });
</script>
<!-- AdminLTE for demo purposes -->
<script src="<?php echo $home;?>dist/js/demo.js"></script>

</body>
</html>