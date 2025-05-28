<!-- DataTables & CSS -->
<link rel="stylesheet" href="<?php echo $home; ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/daterangepicker/daterangepicker.css">

<!-- jsPDF and autoTable -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

<!-- Page Specific Scripts -->
<script>
$(document).ready(function() {
    // PDF Export functionality
    $(document).on('click', '#exportPdfBtn', function() {
        try {
            // Initialize jsPDF
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'mm', 'a4');
            
            // Get all periodic report tables
            $('.periodic-report-table').each(function(index) {
                if (index > 0) {
                    doc.addPage();
                }
                
                // Get table data
                const table = $(this);
                const title = table.find('th:first').text();
                const dateRange = table.find('th:contains("Report Date:")').text();
                const employeeInfo = table.find('th:contains("Emp. ID:")').text();
                
                // Add title and info
                doc.setFontSize(16);
                doc.text(title, 20, 20);
                doc.setFontSize(12);
                doc.text(dateRange, 20, 30);
                doc.text(employeeInfo, 20, 40);
                
                // Get table data for autoTable
                const tableData = [];
                const headers = [];
                
                // Get headers
                $(this).find('thead tr:last th').each(function() {
                    headers.push($(this).text());
                });
                
                // Get rows
                $(this).find('tbody tr').each(function() {
                    const row = [];
                    $(this).find('td').each(function() {
                        row.push($(this).text());
                    });
                    tableData.push(row);
                });
                
                // Add table using autoTable
                doc.autoTable({
                    head: [headers],
                    body: tableData,
                    startY: 50,
                    margin: { left: 20 },
                    styles: {
                        fontSize: 8,
                        cellPadding: 2,
                    },
                    headStyles: {
                        fillColor: [52, 58, 64],
                        textColor: 255,
                        fontStyle: 'bold'
                    },
                    alternateRowStyles: {
                        fillColor: [245, 245, 245]
                    }
                });
                
                // Add summary
                const summaryRow = $(this).find('tfoot tr');
                const summaryText = summaryRow.find('th').map(function() {
                    return $(this).text();
                }).get().join(' | ');
                
                doc.setFontSize(10);
                doc.text(summaryText, 20, doc.autoTable.previous.finalY + 10);
            });
            
            // Save the PDF
            doc.save('periodic_report_' + moment().format('YYYY-MM-DD') + '.pdf');
        } catch (error) {
            console.error('Error generating PDF:', error);
            alert('Error generating PDF. Please check console for details.');
        }
    });
});
</script> 