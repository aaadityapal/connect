/**
 * Labour Charts - Direct chart initialization for HR dashboard
 */

// Function to initialize labour distribution chart
function initializeLabourDistributionChart(chartId, companyCount, vendorCount) {
    const canvas = document.getElementById(chartId);
    if (!canvas) {
        console.error('Labour distribution chart canvas not found:', chartId);
        return;
    }
    
    try {
        const ctx = canvas.getContext('2d');
        const total = companyCount + vendorCount;
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Company Labours', 'Vendor Labours'],
                datasets: [{
                    data: [companyCount, vendorCount],
                    backgroundColor: ['#10B981', '#3B82F6'],
                    borderColor: ['#ffffff', '#ffffff'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
        console.log('Labour distribution chart initialized successfully');
    } catch (e) {
        console.error('Error initializing labour distribution chart:', e);
    }
}

// Function to initialize attendance status chart
function initializeAttendanceStatusChart(chartId, morningOnly, eveningOnly, fullDay) {
    const canvas = document.getElementById(chartId);
    if (!canvas) {
        console.error('Attendance status chart canvas not found:', chartId);
        return;
    }
    
    try {
        const ctx = canvas.getContext('2d');
        const total = morningOnly + eveningOnly + fullDay;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Morning Only', 'Evening Only', 'Full Day'],
                datasets: [{
                    label: 'Number of Labourers',
                    data: [morningOnly, eveningOnly, fullDay],
                    backgroundColor: [
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(79, 70, 229, 0.7)',
                        'rgba(16, 185, 129, 0.7)'
                    ],
                    borderColor: [
                        'rgb(245, 158, 11)',
                        'rgb(79, 70, 229)',
                        'rgb(16, 185, 129)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                const percentage = Math.round((value / total) * 100);
                                return `${value} labourers (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        console.log('Attendance status chart initialized successfully');
    } catch (e) {
        console.error('Error initializing attendance status chart:', e);
    }
}

// Function to calculate attendance statistics from labour data
function calculateAttendanceStats(companyLabours, vendorLabours) {
    let morningOnly = 0;
    let eveningOnly = 0;
    let fullDay = 0;
    
    // Process company labours
    if (companyLabours && companyLabours.length > 0) {
        companyLabours.forEach(labour => {
            if (labour.morning_attendance && labour.evening_attendance) {
                fullDay++;
            } else if (labour.morning_attendance) {
                morningOnly++;
            } else if (labour.evening_attendance) {
                eveningOnly++;
            }
        });
    }
    
    // Process vendor labours
    if (vendorLabours && vendorLabours.length > 0) {
        vendorLabours.forEach(labour => {
            if (labour.morning_attendance && labour.evening_attendance) {
                fullDay++;
            } else if (labour.morning_attendance) {
                morningOnly++;
            } else if (labour.evening_attendance) {
                eveningOnly++;
            }
        });
    }
    
    return {
        morningOnly,
        eveningOnly,
        fullDay
    };
} 