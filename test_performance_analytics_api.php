<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Analytics API Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
        .success { background: #d4edda; border-color: #c3e6cb; }
        .error { background: #f8d7da; border-color: #f5c6cb; }
        .loading { background: #fff3cd; border-color: #ffeaa7; }
        pre { background: #f8f9fa; padding: 10px; overflow-x: auto; font-size: 0.8rem; }
        .suggestion-preview { 
            background: #f0f9ff; 
            border: 1px solid #0ea5e9; 
            border-radius: 6px; 
            padding: 10px; 
            margin: 10px 0; 
        }
        .suggestion-title { font-weight: bold; color: #0c4a6e; }
        .suggestion-message { color: #374151; margin-top: 5px; }
        .critical { border-color: #ef4444; background: #fef2f2; }
        .warning { border-color: #f59e0b; background: #fffbeb; }
        .success-suggest { border-color: #10b981; background: #f0fdf4; }
    </style>
</head>
<body>
    <h1>Performance Analytics API Test</h1>
    
    <div id="testResults">
        <div class="test-section loading">
            <h3>Testing fetch_detailed_stage_performance_analytics.php</h3>
            <p>Loading...</p>
        </div>
    </div>

    <script>
        function runAPITest() {
            const testResults = document.getElementById('testResults');
            
            fetch('fetch_detailed_stage_performance_analytics.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    let suggestionsPreview = '';
                    if (data.improvement_suggestions && data.improvement_suggestions.length > 0) {
                        suggestionsPreview = '<h4>Sample Suggestions Generated:</h4>';
                        data.improvement_suggestions.forEach(suggestion => {
                            suggestionsPreview += `
                                <div class="suggestion-preview ${suggestion.type}">
                                    <div class="suggestion-title">
                                        <i class="${suggestion.icon}"></i> ${suggestion.title}
                                    </div>
                                    <div class="suggestion-message">${suggestion.message}</div>
                                </div>
                            `;
                        });
                    }
                    
                    testResults.innerHTML = `
                        <div class="test-section success">
                            <h3>✅ Enhanced API Test Successful</h3>
                            <p>The fetch_detailed_stage_performance_analytics.php endpoint is working with enhanced suggestions!</p>
                            <p><strong>Performance Metrics Calculated:</strong></p>
                            <ul>
                                <li>Efficiency: ${data.performance_metrics?.efficiency_percentage || 'N/A'}%</li>
                                <li>Total Completed Tasks: ${data.performance_metrics?.total_completed || 'N/A'}</li>
                                <li>On-time Completions: ${data.performance_metrics?.on_time_completed || 'N/A'}</li>
                                <li>Active Workload: ${data.performance_metrics?.active_workload || 'N/A'}</li>
                            </ul>
                            ${suggestionsPreview}
                            <details>
                                <summary>Full Response Data Structure</summary>
                                <pre>${JSON.stringify(data, null, 2)}</pre>
                            </details>
                        </div>
                    `;
                })
                .catch(error => {
                    testResults.innerHTML = `
                        <div class="test-section error">
                            <h3>❌ API Test Failed</h3>
                            <p>Error: ${error.message}</p>
                            <p>Please check the server logs and ensure:</p>
                            <ul>
                                <li>The database connection is working</li>
                                <li>User session is valid</li>
                                <li>Required tables exist with proper structure</li>
                            </ul>
                        </div>
                    `;
                });
        }

        // Run test when page loads
        document.addEventListener('DOMContentLoaded', runAPITest);
    </script>
</body>
</html>