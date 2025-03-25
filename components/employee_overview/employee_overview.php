<div class="employee-overview-section">
    <div class="overview-grid">
        <!-- Left side stats -->
        <div class="stats-container">
            <div class="stat-row">
                <!-- Present Employees Card -->
                <div class="stat-card present-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Employee Present</h3>
                        <div class="stat-numbers">
                            <span class="number" id="presentCount">28</span>
                            <span class="total">/30</span>
                        </div>
                        <div class="stat-progress">
                            <div class="progress-bar" style="width: 93%"></div>
                        </div>
                    </div>
                </div>

                <!-- Pending Leaves Card -->
                <div class="stat-card pending-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Pending Leaves</h3>
                        <div class="stat-numbers">
                            <span class="number" id="pendingCount">5</span>
                            <span class="label">Requests</span>
                        </div>
                        <div class="stat-change increase">
                            <i class="fas fa-arrow-up"></i> 2 since yesterday
                        </div>
                    </div>
                </div>
            </div>

            <div class="stat-row">
                <!-- Short Leave Card -->
                <div class="stat-card short-leave-card">
                    <div class="stat-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Short Leave</h3>
                        <div class="stat-numbers">
                            <span class="number" id="shortLeaveCount">3</span>
                            <span class="label">Today</span>
                        </div>
                        <div class="stat-change">
                            <i class="fas fa-arrow-right"></i> Normal flow
                        </div>
                    </div>
                </div>

                <!-- On Leave Card -->
                <div class="stat-card on-leave-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-minus"></i>
                    </div>
                    <div class="stat-info">
                        <h3>On Leave</h3>
                        <div class="stat-numbers">
                            <span class="number" id="onLeaveCount">2</span>
                            <span class="label">Today</span>
                        </div>
                        <div class="stat-change decrease">
                            <i class="fas fa-arrow-down"></i> 1 less than yesterday
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right side calendar -->
        <div class="calendar-container">
            <div class="calendar-header">
                <h3>Leave Calendar</h3>
                <div class="calendar-actions">
                    <button class="prev-month"><i class="fas fa-chevron-left"></i></button>
                    <span class="current-month">March 2024</span>
                    <button class="next-month"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            <div class="calendar-body" id="calendarBody">
                <!-- Calendar will be populated by JavaScript -->
            </div>
            <div class="calendar-legend">
                <div class="legend-item">
                    <span class="legend-dot approved"></span>
                    <span>Approved Leave</span>
                </div>
                <div class="legend-item">
                    <span class="legend-dot pending"></span>
                    <span>Pending</span>
                </div>
                <div class="legend-item">
                    <span class="legend-dot holiday"></span>
                    <span>Holiday</span>
                </div>
            </div>
        </div>
    </div>
</div> 