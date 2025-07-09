<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee 3D Visualization Payouts</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2980b9;
            --dark: #2c3e50;
            --light: #ecf0f1;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --company-blue: #3498db;
            --company-dark: #2c3e50;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            text-align: center;
            margin-bottom: 3rem;
            animation: fadeIn 1s ease;
            background-color: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-left: 5px solid var(--company-blue);
        }
        
        h1 {
            font-size: 2.5rem;
            color: var(--company-dark);
            margin-bottom: 1rem;
            position: relative;
            display: inline-block;
        }
        
        h1::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: var(--company-blue);
            border-radius: 2px;
        }
        
        .subtitle {
            font-size: 1.1rem;
            color: var(--dark);
            opacity: 0.8;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .employee-info {
            display: flex;
            justify-content: space-between;
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            align-items: center;
        }
        
        .employee-details {
            display: flex;
            align-items: center;
        }
        
        .employee-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--company-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-right: 1rem;
        }
        
        .employee-name {
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .employee-position {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .payout-summary {
            background: var(--company-blue);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .payout-amount {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .payout-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        .pricing-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            background: white;
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .tab-btn {
            padding: 0.8rem 2rem;
            margin: 0 0.5rem 1rem;
            background: #f8f9fa;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--dark);
        }
        
        .tab-btn.active {
            background: var(--company-blue);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
        }
        
        .tab-btn:hover:not(.active) {
            background: #e0e0e0;
        }
        
        .pricing-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .pricing-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-top: 4px solid var(--company-blue);
        }
        
        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background: #f8f9fa;
            color: var(--dark);
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        
        .card-header h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .price-item {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .price-item:last-child {
            border-bottom: none;
        }
        
        .price-label {
            font-weight: 500;
            color: var(--dark);
        }
        
        .price-amount {
            font-weight: 600;
            color: var(--company-blue);
        }
        
        .price-amount.small {
            color: #7f8c8d;
        }
        
        .card-footer {
            padding: 1.5rem;
            background: #f8f9fa;
            text-align: center;
            border-top: 1px solid #eee;
        }
        
        .total-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--success);
        }
        
        .performance-badge {
            display: inline-block;
            background: var(--success);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .performance-warning {
            background: var(--warning);
        }
        
        .performance-excellent {
            background: var(--success);
        }
        
        .performance-outstanding {
            background: linear-gradient(135deg, #2ecc71 0%, #3498db 100%);
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--company-blue);
            margin: 0.5rem 0;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .action-buttons {
            display: flex;
            justify-content: flex-end;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-left: 1rem;
        }
        
        .btn-primary {
            background: var(--company-blue);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: var(--dark);
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .pricing-cards {
                grid-template-columns: 1fr;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .employee-info {
                flex-direction: column;
                text-align: center;
            }
            
            .employee-details {
                margin-bottom: 1rem;
                flex-direction: column;
                text-align: center;
            }
            
            .employee-avatar {
                margin-right: 0;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>3D Visualization Employee Payouts</h1>
            <p class="subtitle">Fair compensation for your creative work. See payout rates for different project types below.</p>
        </header>
        
        <div class="employee-info">
            <div class="employee-details">
                <div class="employee-avatar">JD</div>
                <div>
                    <div class="employee-name">John Designer</div>
                    <div class="employee-position">Senior 3D Visualizer</div>
                </div>
            </div>
            <div class="payout-summary">
                <div class="payout-label">YTD EARNINGS</div>
                <div class="payout-amount">$24,850</div>
            </div>
        </div>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">Projects Completed</div>
                <div class="stat-value">42</div>
                <div class="performance-badge performance-excellent">+18%</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Avg. Rating</div>
                <div class="stat-value">4.8</div>
                <div class="performance-badge performance-outstanding">Top 10%</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">This Month's Payout</div>
                <div class="stat-value">$3,240</div>
                <div class="performance-badge">On track</div>
            </div>
        </div>
        
        <div class="pricing-tabs">
            <button class="tab-btn active" data-tab="residential">Residential</button>
            <button class="tab-btn" data-tab="commercial">Commercial</button>
            <button class="tab-btn" data-tab="exterior">Exterior</button>
        </div>
        
        <div id="residential" class="tab-content active">
            <div class="pricing-cards">
                <div class="pricing-card">
                    <div class="card-header">
                        <h3>Standard Residential</h3>
                        <p>Base compensation per room</p>
                    </div>
                    <div class="card-body">
                        <div class="price-item">
                            <span class="price-label">Living Room</span>
                            <span class="price-amount">$120 - $180</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Bedroom</span>
                            <span class="price-amount">$100 - $150</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Kitchen</span>
                            <span class="price-amount">$150 - $220</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Bathroom</span>
                            <span class="price-amount">$90 - $140</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Dining Room</span>
                            <span class="price-amount">$110 - $160</span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <span>Typical project payout:</span>
                        <span class="total-price">$570 - $850</span>
                    </div>
                </div>
                
                <div class="pricing-card">
                    <div class="card-header">
                        <h3>Premium Residential</h3>
                        <p>High-end residential spaces (20% bonus)</p>
                    </div>
                    <div class="card-body">
                        <div class="price-item">
                            <span class="price-label">Living Room</span>
                            <span class="price-amount">$200 - $300</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Master Bedroom</span>
                            <span class="price-amount">$180 - $250</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Luxury Kitchen</span>
                            <span class="price-amount">$250 - $350</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Spa Bathroom</span>
                            <span class="price-amount">$150 - $220</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Formal Dining</span>
                            <span class="price-amount">$180 - $240</span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <span>Typical project payout:</span>
                        <span class="total-price">$960 - $1,360</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="commercial" class="tab-content">
            <div class="pricing-cards">
                <div class="pricing-card">
                    <div class="card-header">
                        <h3>Standard Commercial</h3>
                        <p>Base compensation per space</p>
                    </div>
                    <div class="card-body">
                        <div class="price-item">
                            <span class="price-label">Office Space</span>
                            <span class="price-amount">$250 - $350</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Retail Store</span>
                            <span class="price-amount">$300 - $400</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Restaurant (Dining)</span>
                            <span class="price-amount">$350 - $450</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Hotel Lobby</span>
                            <span class="price-amount">$400 - $500</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Conference Room</span>
                            <span class="price-amount">$200 - $300</span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <span>Typical project payout:</span>
                        <span class="total-price">$1,500 - $2,000</span>
                    </div>
                </div>
                
                <div class="pricing-card">
                    <div class="card-header">
                        <h3>Premium Commercial</h3>
                        <p>High-end commercial (25% bonus)</p>
                    </div>
                    <div class="card-body">
                        <div class="price-item">
                            <span class="price-label">Executive Office</span>
                            <span class="price-amount">$450 - $600</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Luxury Boutique</span>
                            <span class="price-amount">$500 - $700</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Fine Dining Restaurant</span>
                            <span class="price-amount">$600 - $800</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">5-Star Hotel Suite</span>
                            <span class="price-amount">$700 - $900</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Board Room</span>
                            <span class="price-amount">$400 - $550</span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <span>Typical project payout:</span>
                        <span class="total-price">$2,650 - $3,550</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="exterior" class="tab-content">
            <div class="pricing-cards">
                <div class="pricing-card">
                    <div class="card-header">
                        <h3>Residential Exterior</h3>
                        <p>Base compensation per project</p>
                    </div>
                    <div class="card-body">
                        <div class="price-item">
                            <span class="price-label">Single Family Home</span>
                            <span class="price-amount">$500 - $750</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Townhouse</span>
                            <span class="price-amount">$600 - $850</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Villa</span>
                            <span class="price-amount">$800 - $1,200</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Residential Landscape</span>
                            <span class="price-amount">$400 - $600</span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <span>Typical project payout:</span>
                        <span class="total-price">$2,300 - $3,400</span>
                    </div>
                </div>
                
                <div class="pricing-card">
                    <div class="card-header">
                        <h3>Commercial Exterior</h3>
                        <p>High-end commercial (30% bonus)</p>
                    </div>
                    <div class="card-body">
                        <div class="price-item">
                            <span class="price-label">Office Building</span>
                            <span class="price-amount">$1,200 - $1,800</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Shopping Complex</span>
                            <span class="price-amount">$1,500 - $2,200</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Hotel Exterior</span>
                            <span class="price-amount">$1,800 - $2,500</span>
                        </div>
                        <div class="price-item">
                            <span class="price-label">Commercial Landscape</span>
                            <span class="price-amount">$900 - $1,400</span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <span>Typical project payout:</span>
                        <span class="total-price">$5,400 - $7,900</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="action-buttons">
            <button class="btn btn-secondary">View Payment History</button>
            <button class="btn btn-primary">Submit Work for Review</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    // Remove active class from all buttons and contents
                    tabBtns.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked button
                    btn.classList.add('active');
                    
                    // Show corresponding content
                    const tabId = btn.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Add animation to cards when they come into view
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = 'fadeIn 0.8s ease forwards';
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            
            document.querySelectorAll('.pricing-card, .stat-card').forEach(card => {
                card.style.opacity = '0';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>