<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Search Freebies</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            max-width: 800px;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        input[type="text"] {
            padding: 8px;
            width: 300px;
            margin-right: 10px;
        }
        button {
            padding: 8px 15px;
            background: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background: #45a049;
        }
        #result {
            margin-top: 15px;
            padding: 10px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 3px;
            min-height: 50px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Test Search Freebies Endpoint</h1>
    
    <div class="test-section">
        <h3>Search Test</h3>
        <p>Enter a search term to test the search_freebies.php endpoint:</p>
        <input type="text" id="searchTerm" placeholder="e.g., charger, case, cable">
        <button onclick="testSearch()">Test Search</button>
        
        <div id="result"></div>
    </div>

    <script>
        function testSearch() {
            const searchTerm = document.getElementById('searchTerm').value.trim();
            const resultDiv = document.getElementById('result');
            
            if (!searchTerm) {
                resultDiv.innerHTML = '<span class="error">Please enter a search term</span>';
                return;
            }
            
            resultDiv.innerHTML = 'Searching...';
            
            fetch(`search_freebies.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers.get('content-type'));
                    
                    // Clone and read as text first
                    return response.clone().text().then(text => {
                        console.log('Raw response:', text);
                        console.log('Response length:', text.length);
                        
                        if (!text || text.trim() === '') {
                            throw new Error('Empty response from server');
                        }
                        
                        try {
                            const json = JSON.parse(text);
                            return json;
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            throw new Error('Invalid JSON: ' + text.substring(0, 100));
                        }
                    });
                })
                .then(data => {
                    console.log('Parsed data:', data);
                    
                    let html = '<strong>Response:</strong>\n\n';
                    html += JSON.stringify(data, null, 2);
                    
                    if (data.status === 'success') {
                        html = '<span class="success">✓ SUCCESS</span>\n\n' + html;
                        if (data.data && data.data.length > 0) {
                            html += `\n\nFound ${data.data.length} item(s) with zero stock`;
                        } else {
                            html += '\n\nNo items found with zero stock at your branch';
                        }
                    } else {
                        html = '<span class="error">✗ ERROR</span>\n\n' + html;
                    }
                    
                    resultDiv.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultDiv.innerHTML = `<span class="error">✗ ERROR</span>\n\n${error.message}\n\nCheck browser console for details`;
                });
        }
        
        // Allow Enter key
        document.getElementById('searchTerm').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                testSearch();
            }
        });
    </script>
</body>
</html>
