/**
 * Periodic Report DataTables Configuration
 * Enhances and standardizes the DataTables setup for periodic attendance reports
 */

const initMonthlyReportTables = () => {    // Initialize DataTables for each report table
    $(".periodic-report-table").each(function() {
        // Extract employee information for title personalization
        const tableId = $(this).attr('id');
        const employeeId = tableId ? tableId.split('-')[1] : '';
        const employeeName = $(this).find('thead tr:nth-child(3) th:nth-child(2)').text().replace('Name:', '').trim();
        
        // Initialize DataTable with optimized configuration
        const table = $(this).DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "paging": false,
            "searching": false,
            "ordering": false,
            "info": false,
            "buttons": [
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-columns"></i> Columns',
                    className: 'btn-sm btn-outline-secondary'
                },
                {
                    extend: 'copyHtml5',
                    text: '<i class="fas fa-copy"></i> Copy',
                    className: 'btn-sm btn-outline-secondary',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    className: 'btn-sm btn-outline-success',
                    title: 'Periodic Attendance - ' + employeeName,
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn-sm btn-outline-danger',
                    title: 'Periodic Attendance - ' + employeeName,
                    orientation: 'landscape',
                    pageSize: 'A4',
                    exportOptions: {
                        columns: ':visible'
                    },
                    customize: function(doc) {
                        // Optimize PDF appearance
                        doc.defaultStyle.fontSize = 8;
                        doc.styles.tableHeader.fontSize = 9;
                        doc.pageMargins = [20, 30, 20, 30];
                        
                        // Add company info
                        doc.content.splice(0, 0, {
                            text: 'Prime Express Courier & Cargo Pvt Ltd',
                            style: {
                                alignment: 'center',
                                fontSize: 14,
                                bold: true,
                                margin: [0, 0, 0, 10]
                            }
                        });
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Print',
                    className: 'btn-sm btn-outline-primary',
                    title: 'Periodic Attendance - ' + employeeName,
                    exportOptions: {
                        columns: ':visible'
                    },
                    customize: function(win) {
                        // Optimize print appearance
                        $(win.document.body).css('font-size', '10pt');
                        $(win.document.body).find('table')
                            .addClass('compact')
                            .css('font-size', 'inherit');
                        
                        // Add landscape printing
                        const css = '@page { size: landscape; }';
                        const head = win.document.head || win.document.getElementsByTagName('head')[0];
                        const style = win.document.createElement('style');
                        style.type = 'text/css';
                        style.media = 'print';
                        
                        if (style.styleSheet) {
                            style.styleSheet.cssText = css;
                        } else {
                            style.appendChild(win.document.createTextNode(css));
                        }
                        head.appendChild(style);
                    }
                }
            ],
            "language": {
                "emptyTable": "No data available for this employee in the selected period.",
                "zeroRecords": "No matching records found"
            }
        });

        // Move buttons to the container div in the card body
        table.buttons().container().appendTo($(this).closest('.card-body').find('.dt-buttons'));
    });
};

// Initialize DateRangePicker with enhanced options
const initDateRangePicker = () => {
    const dateRangeInput = $('#reportDateRange');
    const initialDateRange = dateRangeInput.val();
    
    // Default to last month
    let startDate = moment().subtract(1, 'month').startOf('month');
    let endDate = moment().subtract(1, 'month').endOf('month');

    // If there's an initial value, parse it
    if (initialDateRange) {
        const dates = initialDateRange.split(' - ');
        if (dates.length === 2) {
            startDate = moment(dates[0], 'DD/MM/YYYY');
            endDate = moment(dates[1], 'DD/MM/YYYY');
        }
    }

    dateRangeInput.daterangepicker({
        locale: {
            format: 'DD/MM/YYYY',
            applyLabel: 'Apply',
            cancelLabel: 'Clear',
            fromLabel: 'From',
            toLabel: 'To',
            customRangeLabel: 'Custom Range'
        },
        opens: 'right',
        startDate: startDate,
        endDate: endDate,
        maxDate: moment(),
        autoApply: false,
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
            'This Quarter': [moment().startOf('quarter'), moment().endOf('quarter')],
            'Last Quarter': [moment().subtract(1, 'quarter').startOf('quarter'), moment().subtract(1, 'quarter').endOf('quarter')]
        }
    });
    
    // Handle cancel button (clear input)
    dateRangeInput.on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
    });    // Form reset handler
    $('#periodic-report-form').on('reset', function() {
        setTimeout(function() {
            dateRangeInput.val('');
        }, 10);
    });
};

// Force reload page without cache
const forceRefresh = () => {
    const timestamp = new Date().getTime();
    window.location.href = window.location.pathname + '?nocache=' + timestamp;
};

// Run initialization on document ready
$(document).ready(function() {
    initMonthlyReportTables();
    initDateRangePicker();
    
    // Add print button functionality
    $('#printReport').on('click', function() {
        window.print();
    });
    
    // Add refresh button functionality
    $('#refreshReport').on('click', function() {
        forceRefresh();
    });
});
