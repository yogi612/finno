<!-- ...existing code... -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIBIL Score Fetcher</title>
    <!-- Tailwind CSS for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- html2pdf.js for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" xintegrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        /* Custom styles for the page */
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Custom animation for the score circle */
        @keyframes fill-progress {
            from { stroke-dashoffset: 314; }
            to { stroke-dashoffset: var(--stroke-offset); }
        }
        .progress-ring__circle--animated {
            animation: fill-progress 2s ease-in-out forwards;
        }
        /* Styles for printing/PDF generation */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body class="bg-gray-100 antialiased">

    <!-- Main Container -->
    <div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-4xl">
        <!-- Header -->
        <header class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">CIBIL Credit Report</h1>
            <p class="text-gray-500">Instant, secure, and detailed credit report for your PAN.</p>
        </header>

        <main id="mainContent" class="bg-white p-8 rounded-2xl shadow-xl transition-all duration-500">
            <!-- Form Section -->
            <div id="formSection">
                <form id="cibilForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="pan" class="block text-sm font-medium text-gray-700 mb-1">PAN Card</label>
                            <input type="text" id="pan" name="pan" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow" placeholder="ABCDE1234F" required pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" title="Enter a valid PAN number">
                        </div>
                        <div>
                            <label for="fullName" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" id="fullName" name="fullName" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow" placeholder="e.g., Rohan Kumar" required>
                        </div>
                        <div>
                            <label for="dob" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                            <input type="date" id="dob" name="dob" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow" required>
                        </div>
                        <div>
                            <label for="mobile" class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
                            <input type="tel" id="mobile" name="mobile" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow" placeholder="10-digit mobile number" required pattern="[6-9]{1}[0-9]{9}" title="Enter a valid 10-digit mobile number">
                        </div>
                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address (Optional)</label>
                            <textarea id="address" name="address" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow" placeholder="Your current address" rows="2"></textarea>
                        </div>
                        <div>
                            <label for="pincode" class="block text-sm font-medium text-gray-700 mb-1">Pincode (Optional)</label>
                            <input type="text" id="pincode" name="pincode" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow" placeholder="6-digit pincode" pattern="[0-9]{6}" title="Enter a valid 6-digit pincode">
                        </div>
                    </div>
                    <div class="mt-6 flex items-start">
                        <input type="checkbox" id="terms" name="terms" class="h-4 w-4 mt-1 text-blue-600 border-gray-300 rounded focus:ring-blue-500" required>
                        <label for="terms" class="ml-3 block text-sm text-gray-600">
                            I agree to the terms and conditions and authorize the fetching of my credit report.
                        </label>
                    </div>
                    <div class="mt-8 text-center">
                        <button type="submit" class="w-full sm:w-auto bg-blue-600 text-white font-bold py-3 px-10 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 transition-all transform hover:scale-105">
                            Fetch My Score
                        </button>
                    </div>
                </form>
            </div>
            <div id="loadingSection" class="hidden text-center py-16">
                <div class="inline-block animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-500"></div>
                <p class="mt-4 text-lg font-medium text-gray-700">Fetching your report...</p>
                <p class="text-gray-500">This may take a moment. Please wait.</p>
            </div>
            <div id="errorSection" class="hidden text-center py-16">
                <div class="inline-block rounded-full h-16 w-16 flex items-center justify-center bg-blue-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="mt-4 text-lg font-medium text-gray-700" id="errorMessage">Unable to fetch your report</p>
                <p id="errorDetails" class="text-gray-500 mb-2 hidden"></p>
                <p class="text-gray-500 mb-6">Please try again later or contact support.</p>
                <button onclick="resetPage()" class="bg-blue-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-blue-700 transition-all">
                    Try Again
                </button>
            </div>
            <div id="resultsSection" class="hidden">
                <div id="reportContent">
                    <div class="text-center mb-6">
                        <h2 class="text-3xl font-bold text-gray-900">Your CIBIL Report</h2>
                        <p class="text-gray-500" id="reportDate"></p>
                    </div>
                    <div class="border-2 border-gray-200 rounded-lg p-6 mb-8 bg-gray-50">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Applicant Details</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                            <p><strong>Full Name:</strong> <span id="userInfoName"></span></p>
                            <p><strong>PAN:</strong> <span id="userInfoPan"></span></p>
                            <p><strong>Date of Birth:</strong> <span id="userInfoDob"></span></p>
                            <p><strong>Mobile:</strong> <span id="userInfoMobile"></span></p>
                            <p id="userInfoAddressContainer" class="hidden"><strong>Address:</strong> <span id="userInfoAddress"></span></p>
                            <p id="userInfoPincodeContainer" class="hidden"><strong>Pincode:</strong> <span id="userInfoPincode"></span></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                        <div class="flex flex-col items-center justify-center">
                            <div class="relative w-40 h-40 mb-4">
                                <svg class="w-full h-full" viewBox="0 0 120 120">
                                    <circle class="stroke-current text-gray-200" cx="60" cy="60" r="50" fill="none" stroke-width="10"></circle>
                                    <circle id="scoreCircle" class="progress-ring__circle--animated stroke-current" cx="60" cy="60" r="50" fill="none" stroke-width="10" stroke-linecap="round" transform="rotate(-90 60 60)" style="stroke-dasharray: 314; --stroke-offset: 314;"></circle>
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span id="cibilScore" class="text-4xl font-bold text-gray-900">0</span>
                                    <span id="cibilRating" class="text-lg font-medium"></span>
                                </div>
                            </div>
                            <p class="text-sm text-gray-500">Score range: 300-900</p>
                        </div>
                        <div class="space-y-4 w-full">
                            <div class="bg-white p-4 rounded-lg shadow-sm"><h3 class="font-semibold text-gray-700">Payment History</h3><p id="paymentHistory" class="text-sm text-gray-600"></p></div>
                            <div class="bg-white p-4 rounded-lg shadow-sm"><h3 class="font-semibold text-gray-700">Credit Utilization</h3><p id="creditUtilization" class="text-sm text-gray-600"></p></div>
                            <div class="bg-white p-4 rounded-lg shadow-sm"><h3 class="font-semibold text-gray-700">Credit Mix</h3><p id="creditMix" class="text-sm text-gray-600"></p></div>
                            <div class="bg-white p-4 rounded-lg shadow-sm"><h3 class="font-semibold text-gray-700">Recent Enquiries</h3><p id="recentEnquiries" class="text-sm text-gray-600"></p></div>
                        </div>
                    </div>
                    <div id="additionalDataContainer" class="mt-10"></div>
                </div>
                <div class="mt-8 text-center space-x-4 no-print">
                    <button onclick="downloadReport('pdf')" class="bg-blue-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-blue-700 transition-all mr-2">
                        Download PDF
                    </button>
                    <button onclick="downloadReport('csv')" class="bg-green-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-green-700 transition-all mr-2">
                        Download CSV
                    </button>
                    <button onclick="downloadReport('excel')" class="bg-indigo-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-indigo-700 transition-all">
                        Download Excel
                    </button>
                    <button onclick="resetPage()" class="bg-gray-200 text-gray-800 font-bold py-2 px-6 rounded-lg hover:bg-gray-300 transition-all">
                        Check Again
                    </button>
                </div>
            </div>
        </main>
        <footer class="text-center mt-8">
            <p class="text-xs text-gray-400">Disclaimer: This is a demo. No real CIBIL data is fetched or stored.</p>
        </footer>
    </div>

    <script>
        // Helper to create a full table from any object (recursive for nested fields)
        function createFullTable(obj, parentKey = '') {
            const table = document.createElement('table');
            table.className = 'min-w-full divide-y divide-gray-200 mb-4';
            const thead = document.createElement('thead');
            thead.className = 'bg-gray-50';
            const headerRow = document.createElement('tr');
            const thKey = document.createElement('th');
            thKey.textContent = 'Field';
            thKey.className = 'px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
            const thValue = document.createElement('th');
            thValue.textContent = 'Value';
            thValue.className = 'px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
            headerRow.appendChild(thKey);
            headerRow.appendChild(thValue);
            thead.appendChild(headerRow);
            table.appendChild(thead);
            const tbody = document.createElement('tbody');
            tbody.className = 'bg-white divide-y divide-gray-200';
            Object.entries(obj).forEach(([key, value]) => {
                const row = document.createElement('tr');
                row.className = 'bg-white';
                const keyCell = document.createElement('td');
                keyCell.className = 'px-3 py-2 whitespace-nowrap text-sm text-gray-900';
                keyCell.textContent = parentKey ? `${parentKey}.${key}` : key;
                const valueCell = document.createElement('td');
                valueCell.className = 'px-3 py-2 whitespace-nowrap text-sm text-gray-900';
                if (Array.isArray(value)) {
                    if (value.length === 0) {
                        valueCell.textContent = '[]';
                    } else {
                        valueCell.appendChild(createFullTable(value[0], key));
                    }
                } else if (typeof value === 'object' && value !== null) {
                    valueCell.appendChild(createFullTable(value, key));
                } else {
                    valueCell.textContent = value;
                }
                row.appendChild(keyCell);
                row.appendChild(valueCell);
                tbody.appendChild(row);
            });
            table.appendChild(tbody);
            return table;
        }
        // Get references to all the necessary elements
        const cibilForm = document.getElementById('cibilForm');
        const formSection = document.getElementById('formSection');
        const loadingSection = document.getElementById('loadingSection');
        const errorSection = document.getElementById('errorSection');
        const errorMessageEl = document.getElementById('errorMessage');
        const resultsSection = document.getElementById('resultsSection');
        const mainContent = document.getElementById('mainContent');
        
        // Score display elements
        const cibilScoreEl = document.getElementById('cibilScore');
        const cibilRatingEl = document.getElementById('cibilRating');
        const scoreCircle = document.getElementById('scoreCircle');
        const reportDateEl = document.getElementById('reportDate');
        
        // Detail elements
        const paymentHistoryEl = document.getElementById('paymentHistory');
        const creditUtilizationEl = document.getElementById('creditUtilization');
        const creditMixEl = document.getElementById('creditMix');
        const recentEnquiriesEl = document.getElementById('recentEnquiries');

        // User Info elements
        const userInfoName = document.getElementById('userInfoName');
        const userInfoPan = document.getElementById('userInfoPan');
        const userInfoDob = document.getElementById('userInfoDob');
        const userInfoMobile = document.getElementById('userInfoMobile');

        // Handle form submission
        cibilForm.addEventListener('submit', function(event) {
            event.preventDefault();
            formSection.classList.add('hidden');
            loadingSection.classList.remove('hidden');
            mainContent.classList.add('py-16');

            const formData = new FormData(cibilForm);
            const userData = {
                fullName: formData.get('fullName'),
                pan: formData.get('pan'),
                dob: formData.get('dob'),
                mobile: formData.get('mobile'),
                address: formData.get('address'),
                pincode: formData.get('pincode')
            };

            // Make an actual API call to fetch CIBIL data
            fetch('fetch_cibil_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(userData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Store the raw API response for display
                const rawApiResponse = data.raw_api_response || JSON.stringify(data, null, 2);
                if (data.status === 'success' && data.result) {
                    // Use all mapped keys from backend
                    const apiResponse = {
                        score: data.result.score || 0,
                        paymentHistory: data.result.paymentHistory || '',
                        creditUtilization: data.result.creditUtilization || '',
                        creditMix: data.result.creditMix || '',
                        recentEnquiries: data.result.recentEnquiries || '',
                        // Enhanced fields
                        personalInfo: data.result.personal_info || {},
                        identityInfo: data.result.identity_info || {},
                        addressInfo: data.result.address_info || [],
                        phoneInfo: data.result.phone_info || [],
                        emailInfo: data.result.email_info || [],
                        accountSummary: data.result.account_summary || {},
                        creditHistory: data.result.credit_history || [],
                        inquiries: data.result.inquiries || [],
                        enquirySummary: data.result.enquiry_summary || {},
                        scoreFactors: data.result.score_factors || [],
                        otherIndicators: data.result.other_indicators || {},
                        recentActivities: data.result.recent_activities || {},
                        // Add raw API response
                        rawApiResponse: rawApiResponse
                    };
                    showResults(apiResponse, userData);
                    enhanceUIWithAdditionalData(apiResponse, rawApiResponse);
                } else {
                    // If API returns an error, show error section with detailed message
                    console.error('API Error:', data);
                    let errorMessage = 'Unable to fetch your credit report';
                    let errorDetails = '';
                    
                    if (data && data.message) {
                        errorMessage = data.message;
                    }
                    
                    // Get error details if available
                    if (data && data.details) {
                        errorDetails = data.details;
                    }
                    
                    // Add code information if available
                    if (data && data.code && data.code !== 'Unknown code') {
                        // Only add code to message if details doesn't already have it
                        if (!errorDetails.includes(data.code)) {
                            errorMessage += ' (Error code: ' + data.code + ')';
                        }
                    }
                    
                    showError(errorMessage, errorDetails);
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                
                // Provide more specific error messages based on the error type
                let errorMessage = 'Network error. Please check your connection and try again.';
                let errorDetails = error.message || '';
                
                if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                    errorMessage = 'Unable to connect to the server. Please check your internet connection.';
                    errorDetails = 'The server may be down or your internet connection may be unstable.';
                } else if (error.name === 'SyntaxError') {
                    errorMessage = 'The server returned an invalid response. Please try again later.';
                    errorDetails = 'The response could not be parsed as valid JSON.';
                } else if (error.name === 'AbortError') {
                    errorMessage = 'Request timed out. Please try again later.';
                    errorDetails = 'The server took too long to respond.';
                } else if (error.message && error.message.includes('HTTP error')) {
                    // Extract status code if available
                    const statusMatch = error.message.match(/Status: (\d+)/);
                    if (statusMatch && statusMatch[1]) {
                        const status = statusMatch[1];
                        if (status === '404') {
                            errorMessage = 'The CIBIL service endpoint was not found. Please contact support.';
                            errorDetails = 'The API endpoint URL may be incorrect or the service may have moved.';
                        } else if (status === '403') {
                            errorMessage = 'Access to the CIBIL service is forbidden. Please contact support.';
                            errorDetails = 'Your API credentials may have expired or been revoked.';
                        } else if (status === '500') {
                            errorMessage = 'The CIBIL server encountered an error. Please try again later.';
                            errorDetails = 'This is a server-side issue that needs to be resolved by the service provider.';
                        } else if (status === '503') {
                            errorMessage = 'The CIBIL service is temporarily unavailable. Please try again later.';
                            errorDetails = 'The service may be undergoing maintenance or experiencing high traffic.';
                        } else {
                            errorMessage = `Server returned an error (${status}). Please try again later.`;
                            errorDetails = 'An unexpected HTTP status code was received from the server.';
                        }
                    }
                }
                
                showError(errorMessage, errorDetails);
            })
            .finally(() => {
                // Hide loading section if it's still visible
                loadingSection.classList.add('hidden');
            });
        });

        /**
         * Displays the results based on API data and user data.
         * @param {object} apiData - The data object from the API.
         * @param {object} userData - The user's form data.
         */
        function showResults(apiData, userData) {
            const { score } = apiData;
            let rating = '';
            let ratingColorClass = '';

            // Determine rating and color (red theme)
            if (score >= 800) {
                rating = 'Excellent';
                ratingColorClass = 'text-red-500';
            } else if (score >= 750) {
                rating = 'Very Good';
                ratingColorClass = 'text-red-500';
            } else if (score >= 700) {
                rating = 'Good';
                ratingColorClass = 'text-orange-500';
            } else if (score > 0) {
                rating = 'Fair';
                ratingColorClass = 'text-orange-600';
            } else {
                rating = 'Not Available';
                ratingColorClass = 'text-gray-500';
            }

            // Populate UI with data
            cibilScoreEl.textContent = score;
            cibilRatingEl.textContent = rating;
            cibilRatingEl.className = `text-lg font-medium ${ratingColorClass}`;
            
            paymentHistoryEl.textContent = apiData.paymentHistory;
            creditUtilizationEl.textContent = apiData.creditUtilization;
            creditMixEl.textContent = apiData.creditMix;
            recentEnquiriesEl.textContent = apiData.recentEnquiries;

            // Populate user info
            userInfoName.textContent = userData.fullName;
            userInfoPan.textContent = userData.pan.toUpperCase();
            userInfoDob.textContent = userData.dob;
            userInfoMobile.textContent = userData.mobile;
            
            // Set address and pincode if provided
            if (userData.address && userData.address.trim() !== '') {
                document.getElementById('userInfoAddress').textContent = userData.address;
                document.getElementById('userInfoAddressContainer').classList.remove('hidden');
            } else {
                document.getElementById('userInfoAddressContainer').classList.add('hidden');
            }
            
            if (userData.pincode && userData.pincode.trim() !== '') {
                document.getElementById('userInfoPincode').textContent = userData.pincode;
                document.getElementById('userInfoPincodeContainer').classList.remove('hidden');
            } else {
                document.getElementById('userInfoPincodeContainer').classList.add('hidden');
            }

            // Set report date
            const today = new Date();
            reportDateEl.textContent = `Report generated on: ${today.toLocaleDateString('en-GB')}`;

            // Animate score circle
            const circumference = 2 * Math.PI * 50;
            
            // Handle 'Not Available' score case
            if (score === 0 || score === '0' || rating === 'Not Available') {
                // For 'Not Available', show empty circle
                scoreCircle.style.setProperty('--stroke-offset', circumference);
            } else {
                // Normal score calculation
                const progress = (score - 300) / 600;
                const strokeOffset = circumference * (1 - progress);
                scoreCircle.style.setProperty('--stroke-offset', strokeOffset);
            }
            
            scoreCircle.classList.add(ratingColorClass);

            // Show results
            loadingSection.classList.add('hidden');
            resultsSection.classList.remove('hidden');
            mainContent.classList.remove('py-16');
        }

        /**
         * Downloads the report in the specified format.
         * @param {string} format - The format to download (pdf, csv, excel)
         */
        function downloadReport(format = 'pdf') {
            const element = document.getElementById('reportContent');
            const pan = document.getElementById('userInfoPan').textContent;
            const userData = {
                name: userInfoName.textContent,
                pan: userInfoPan.textContent,
                dob: userInfoDob.textContent,
                mobile: userInfoMobile.textContent,
                address: document.getElementById('userInfoAddress').textContent,
                pincode: document.getElementById('userInfoPincode').textContent
            };

            // Add Finonest branding and logo
            const finonestLogoUrl = '/assets/logo.png';
            const finonestBranding = 'Finonest - India\'s Trusted Credit Platform';

            // Collect all report data for download
            const reportData = {
                score: cibilScoreEl.textContent,
                rating: cibilRatingEl.textContent,
                paymentHistory: paymentHistoryEl.textContent,
                creditUtilization: creditUtilizationEl.textContent,
                creditMix: creditMixEl.textContent,
                recentEnquiries: recentEnquiriesEl.textContent,
                reportDate: document.getElementById('reportDate').textContent
            };

            // Helper to get additional sections as text, including full table
            function getAdditionalSectionsText() {
                let text = '';
                document.querySelectorAll('#additionalDataContainer .bg-white.rounded-lg.shadow-md').forEach(section => {
                    const title = section.querySelector('button span.text-lg')?.textContent || '';
                    if (title) text += `\n${title}:\n`;
                    // If table exists, extract rows
                    const table = section.querySelector('table');
                    if (table) {
                        table.querySelectorAll('tr').forEach(row => {
                            const cells = row.querySelectorAll('td');
                            if (cells.length === 2) {
                                text += `${cells[0].textContent}: ${cells[1].textContent}\n`;
                            }
                        });
                    }
                    section.querySelectorAll('.flex.justify-between').forEach(item => {
                        const label = item.querySelector('span.text-gray-600')?.textContent || '';
                        const value = item.querySelector('span.font-medium')?.textContent || '';
                        if (label && value) text += `${label} ${value}\n`;
                    });
                });
                return text;
            }

            switch(format) {
                case 'pdf':
                    // Add logo and branding to PDF
                    const pdfElement = element.cloneNode(true);
                    const headerDiv = document.createElement('div');
                    headerDiv.className = 'flex items-center mb-6';
                    headerDiv.innerHTML = `<img src="${finonestLogoUrl}" alt="Finonest Logo" style="height:48px;margin-right:16px;"> <span style="font-size:1.5rem;font-weight:bold;color:#2563eb;">${finonestBranding}</span>`;
                    pdfElement.insertBefore(headerDiv, pdfElement.firstChild);
                    const opt = {
                        margin: 0.5,
                        filename: `Finonest_CIBIL_Report_${pan}.pdf`,
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { scale: 2, useCORS: true },
                        jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
                    };
                    html2pdf().set(opt).from(pdfElement).save();
                    break;

                case 'csv':
                    let csvContent = 'Finonest CIBIL Report\n';
                    csvContent += 'Finonest,India\'s Trusted Credit Platform\n';
                    csvContent += '\nUser Information\n';
                    csvContent += `Name,${userData.name}\n`;
                    csvContent += `PAN,${userData.pan}\n`;
                    csvContent += `Date of Birth,${userData.dob}\n`;
                    csvContent += `Mobile,${userData.mobile}\n`;
                    if (userData.address.trim() !== '') {
                        csvContent += `Address,${userData.address}\n`;
                    }
                    if (userData.pincode.trim() !== '') {
                        csvContent += `Pincode,${userData.pincode}\n`;
                    }
                    csvContent += '\nReport Information\n';
                    csvContent += `Report Date,${reportData.reportDate}\n`;
                    csvContent += `CIBIL Score,${reportData.score}\n`;
                    csvContent += `Rating,${reportData.rating}\n`;
                    csvContent += `Payment History,${reportData.paymentHistory}\n`;
                    csvContent += `Credit Utilization,${reportData.creditUtilization}\n`;
                    csvContent += `Credit Mix,${reportData.creditMix}\n`;
                    csvContent += `Recent Enquiries,${reportData.recentEnquiries}\n`;
                    csvContent += getAdditionalSectionsText();
                    // Create and download CSV file
                    const csvBlob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const csvUrl = URL.createObjectURL(csvBlob);
                    const csvLink = document.createElement('a');
                    csvLink.href = csvUrl;
                    csvLink.setAttribute('download', `Finonest_CIBIL_Report_${pan}.csv`);
                    csvLink.style.display = 'none';
                    document.body.appendChild(csvLink);
                    csvLink.click();
                    document.body.removeChild(csvLink);
                    break;

                case 'excel':
                    let excelContent = 'Finonest CIBIL Report\n';
                    excelContent += 'Finonest,India\'s Trusted Credit Platform\n';
                    excelContent += '\nUser Information\n';
                    excelContent += `Name,${userData.name}\n`;
                    excelContent += `PAN,${userData.pan}\n`;
                    excelContent += `Date of Birth,${userData.dob}\n`;
                    excelContent += `Mobile,${userData.mobile}\n`;
                    if (userData.address.trim() !== '') {
                        excelContent += `Address,${userData.address}\n`;
                    }
                    if (userData.pincode.trim() !== '') {
                        excelContent += `Pincode,${userData.pincode}\n`;
                    }
                    excelContent += '\nReport Information\n';
                    excelContent += `Report Date,${reportData.reportDate}\n`;
                    excelContent += `CIBIL Score,${reportData.score}\n`;
                    excelContent += `Rating,${reportData.rating}\n`;
                    excelContent += `Payment History,${reportData.paymentHistory}\n`;
                    excelContent += `Credit Utilization,${reportData.creditUtilization}\n`;
                    excelContent += `Credit Mix,${reportData.creditMix}\n`;
                    excelContent += `Recent Enquiries,${reportData.recentEnquiries}\n`;
                    excelContent += getAdditionalSectionsText();
                    // Create and download Excel-compatible CSV file
                    const excelBlob = new Blob([excelContent], { type: 'application/vnd.ms-excel' });
                    const excelUrl = URL.createObjectURL(excelBlob);
                    const excelLink = document.createElement('a');
                    excelLink.href = excelUrl;
                    excelLink.setAttribute('download', `Finonest_CIBIL_Report_${pan}.xls`);
                    excelLink.style.display = 'none';
                    document.body.appendChild(excelLink);
                    excelLink.click();
                    document.body.removeChild(excelLink);
                    break;

                default:
                    console.error('Invalid format specified');
            }
        }

        /**
         * Shows an error message to the user.
         * @param {string} message - The error message to display.
         * @param {string} details - Optional detailed error information.
         */
        function showError(message, details) {
            loadingSection.classList.add('hidden');
            errorMessageEl.textContent = message;
            errorSection.classList.remove('hidden');
            mainContent.classList.remove('py-16');
            
            // Set error details if provided
            const errorDetailsElement = document.getElementById('errorDetails');
            if (errorDetailsElement) {
                if (details) {
                    errorDetailsElement.textContent = details;
                    errorDetailsElement.classList.remove('hidden');
                } else {
                    errorDetailsElement.classList.add('hidden');
                }
            }
        }

        /**
         * Resets the page to the initial form state.
         */
        /**
         * Enhances the UI with additional data from the API response
         * @param {object} result - The complete result object from the API
         * @param {string} rawApiResponse - The raw API response as a JSON string
         */
        function enhanceUIWithAdditionalData(result, rawApiResponse) {
            // Create or clear the additional data container
            let additionalDataContainer = document.getElementById('additionalDataContainer');
            if (!additionalDataContainer) {
                additionalDataContainer = document.createElement('div');
                additionalDataContainer.id = 'additionalDataContainer';
                additionalDataContainer.className = 'mt-8 space-y-6';
                const downloadButtons = document.querySelector('.flex.justify-center.space-x-4');
                if (downloadButtons) {
                    downloadButtons.parentNode.insertBefore(additionalDataContainer, downloadButtons);
                } else {
                    resultsSection.appendChild(additionalDataContainer);
                }
            } else {
                additionalDataContainer.innerHTML = '';
            }

            // Helper for expandable section
            function createExpandableSection(title, contentEl) {
                const section = document.createElement('div');
                section.className = 'bg-white rounded-lg shadow-md p-0';
                const header = document.createElement('button');
                header.className = 'w-full text-left px-6 py-4 border-b flex justify-between items-center focus:outline-none';
                header.innerHTML = `<span class='text-lg font-semibold text-gray-800'>${title}</span><span class='toggle-arrow text-gray-500'>&#9654;</span>`;
                let expanded = false;
                const contentWrapper = document.createElement('div');
                contentWrapper.className = 'px-6 pb-6 hidden';
                contentWrapper.appendChild(contentEl);
                header.addEventListener('click', function() {
                    expanded = !expanded;
                    contentWrapper.classList.toggle('hidden', !expanded);
                    header.querySelector('.toggle-arrow').innerHTML = expanded ? '&#9660;' : '&#9654;';
                });
                section.appendChild(header);
                section.appendChild(contentWrapper);
                return section;
            }

            // Personal Info
            if (result.personal_info && Object.keys(result.personal_info).length > 0) {
                const personalInfoContent = document.createElement('div');
                personalInfoContent.className = 'grid grid-cols-1 md:grid-cols-2 gap-4';
                Object.entries(result.personal_info).forEach(([key, value]) => {
                    const itemEl = document.createElement('div');
                    itemEl.className = 'flex justify-between border-b pb-2';
                    const labelEl = document.createElement('span');
                    labelEl.className = 'text-gray-600';
                    labelEl.textContent = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + ':';
                    const valueEl = document.createElement('span');
                    valueEl.className = 'font-medium text-gray-800';
                    valueEl.textContent = value;
                    itemEl.appendChild(labelEl);
                    itemEl.appendChild(valueEl);
                    personalInfoContent.appendChild(itemEl);
                });
                additionalDataContainer.appendChild(createExpandableSection('Personal Information', personalInfoContent));
            }

            // Account Summary
            if (result.account_summary && Object.keys(result.account_summary).length > 0) {
                additionalDataContainer.appendChild(createExpandableSection('Account Summary', createAccountSummaryContent(result.account_summary)));
            }

            // Credit History
            if (result.credit_history && result.credit_history.length > 0) {
                additionalDataContainer.appendChild(createExpandableSection('Credit History', createCreditHistoryContent(result.credit_history)));
            }

            // Inquiries
            if (result.inquiries && result.inquiries.length > 0) {
                additionalDataContainer.appendChild(createExpandableSection('Inquiry History', createInquiryContent(result.inquiries)));
            }

            // Score Factors
            if (result.score_factors && result.score_factors.length > 0) {
                additionalDataContainer.appendChild(createExpandableSection('Score Factors', createScoreFactorsContent(result.score_factors)));
            }

            // Other Indicators
            if (result.other_indicators && Object.keys(result.other_indicators).length > 0) {
                additionalDataContainer.appendChild(createExpandableSection('Other Credit Indicators', createOtherIndicatorsContent(result.other_indicators)));
            }

            // Raw API Response
            if (rawApiResponse) {
                additionalDataContainer.appendChild(createExpandableSection('Raw API Response', createRawApiResponseContent(rawApiResponse)));
                // Add full table at the bottom
                let fullTableSection = document.createElement('div');
                try {
                    fullTableSection.appendChild(createFullTable(JSON.parse(rawApiResponse)));
                } catch (e) {
                    fullTableSection.textContent = 'Unable to parse full response.';
                }
                additionalDataContainer.appendChild(createExpandableSection('Complete API Response Table', fullTableSection));
            }
        }
        
        /**
         * Creates a section with title and content
         * @param {string} title - The section title
         * @param {HTMLElement} content - The content element
         * @return {HTMLElement} The complete section element
         */
        function createSection(title, content) {
            const section = document.createElement('div');
            section.className = 'bg-white rounded-lg shadow-md p-6';
            
            const titleEl = document.createElement('h3');
            titleEl.className = 'text-lg font-semibold text-gray-800 mb-4';
            titleEl.textContent = title;
            
            section.appendChild(titleEl);
            section.appendChild(content);
            
            return section;
        }
        
        /**
         * Creates account summary content
         * @param {object} accountSummary - The account summary data
         * @return {HTMLElement} The formatted content
         */
        function createAccountSummaryContent(accountSummary) {
            const container = document.createElement('div');
            container.className = 'grid grid-cols-1 md:grid-cols-2 gap-4';
            
            const items = [
                { label: 'Total Accounts', value: accountSummary.total_accounts || '0' },
                { label: 'Open Accounts', value: accountSummary.open_accounts || '0' },
                { label: 'Closed Accounts', value: accountSummary.closed_accounts || '0' },
                { label: 'Total Balance', value: '₹' + (accountSummary.total_balance || '0') },
                { label: 'Total Credit Limit', value: '₹' + (accountSummary.total_credit_limit || '0') },
                { label: 'Total Monthly Payment', value: '₹' + (accountSummary.total_monthly_payment || '0') },
                { label: 'Recent Account', value: accountSummary.recent_account || 'N/A' },
                { label: 'Oldest Account', value: accountSummary.oldest_account || 'N/A' },
                { label: 'Past Due Accounts', value: accountSummary.past_due_accounts || '0' }
            ];
            
            items.forEach(item => {
                const itemEl = document.createElement('div');
                itemEl.className = 'flex justify-between border-b pb-2';
                
                const labelEl = document.createElement('span');
                labelEl.className = 'text-gray-600';
                labelEl.textContent = item.label + ':';
                
                const valueEl = document.createElement('span');
                valueEl.className = 'font-medium text-gray-800';
                valueEl.textContent = item.value;
                
                itemEl.appendChild(labelEl);
                itemEl.appendChild(valueEl);
                container.appendChild(itemEl);
            });
            
            return container;
        }
        
        /**
         * Creates credit history content
         * @param {array} creditHistory - The credit history data
         * @return {HTMLElement} The formatted content
         */
        function createCreditHistoryContent(creditHistory) {
            const container = document.createElement('div');
            container.className = 'overflow-x-auto';
            
            const table = document.createElement('table');
            table.className = 'min-w-full divide-y divide-gray-200';
            
            // Create table header
            const thead = document.createElement('thead');
            thead.className = 'bg-gray-50';
            
            const headerRow = document.createElement('tr');
            ['Lender', 'Type', 'Amount', 'Balance', 'Status', 'Opened', 'Last Reported'].forEach(text => {
                const th = document.createElement('th');
                th.className = 'px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
                th.textContent = text;
                headerRow.appendChild(th);
            });
            
            thead.appendChild(headerRow);
            table.appendChild(thead);
            
            // Create table body
            const tbody = document.createElement('tbody');
            tbody.className = 'bg-white divide-y divide-gray-200';
            
            creditHistory.forEach((account, index) => {
                const row = document.createElement('tr');
                row.className = index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                
                // Lender
                const lenderCell = document.createElement('td');
                lenderCell.className = 'px-3 py-2 whitespace-nowrap text-sm text-gray-900';
                lenderCell.textContent = account.lender || 'N/A';
                row.appendChild(lenderCell);
                
                // Account Type
                const typeCell = document.createElement('td');
                typeCell.className = 'px-3 py-2 whitespace-nowrap text-sm text-gray-900';
                typeCell.textContent = account.account_type || 'N/A';
                row.appendChild(typeCell);
                
                // Loan Amount
                const amountCell = document.createElement('td');
                amountCell.className = 'px-3 py-2 whitespace-nowrap text-sm text-gray-900';
                amountCell.textContent = '₹' + (account.loan_amount || '0');
                row.appendChild(amountCell);
                
                // Current Balance
                const balanceCell = document.createElement('td');
                balanceCell.className = 'px-3 py-2 whitespace-nowrap text-sm text-gray-900';
                balanceCell.textContent = '₹' + (account.current_balance || '0');
                row.appendChild(balanceCell);
                
                // Status
                const statusCell = document.createElement('td');
                statusCell.className = 'px-3 py-2 whitespace-nowrap text-sm';
                
                // Set color based on status
                const status = account.status || 'N/A';
                const statusLower = status.toLowerCase();
                if (statusLower.includes('current') || statusLower.includes('paid')) {
                    statusCell.className += ' text-green-600';
                } else if (statusLower.includes('late') || statusLower.includes('overdue')) {
                    statusCell.className += ' text-red-600';
                } else {
                    statusCell.className += ' text-gray-900';
                }
                
                statusCell.textContent = status;
                row.appendChild(statusCell);
                
                // Date Opened
                const openedCell = document.createElement('td');
                openedCell.className = 'px-3 py-2 whitespace-nowrap text-sm text-gray-900';
                openedCell.textContent = account.date_opened || 'N/A';
                row.appendChild(openedCell);
                
                // Date Reported
                const reportedCell = document.createElement('td');
                reportedCell.className = 'px-3 py-2 whitespace-nowrap text-sm text-gray-900';
                reportedCell.textContent = account.date_reported || 'N/A';
                row.appendChild(reportedCell);
                
                tbody.appendChild(row);
            });
            
            table.appendChild(tbody);
            container.appendChild(table);
            
            return container;
        }
        
        /**
         * Creates inquiry content
         * @param {array} inquiries - The inquiries data
         * @return {HTMLElement} The formatted content
         */
        function createInquiryContent(inquiries) {
            const container = document.createElement('div');
            container.className = 'overflow-x-auto';
            
            const table = document.createElement('table');
            table.className = 'min-w-full divide-y divide-gray-200';
            
            // Create table header
            const thead = document.createElement('thead');
            thead.className = 'bg-gray-50';
            
            const headerRow = document.createElement('tr');
            ['Institution', 'Date', 'Time', 'Purpose'].forEach(text => {
                const th = document.createElement('th');
                th.className = 'px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
                th.textContent = text;
                headerRow.appendChild(th);
            });
            
            thead.appendChild(headerRow);
            table.appendChild(thead);
            
            // Create table body
            const tbody = document.createElement('tbody');
            tbody.className = 'bg-white divide-y divide-gray-200';
            
            inquiries.forEach((inquiry, index) => {
                const row = document.createElement('tr');
                row.className = index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                
                // Institution
                const institutionCell = document.createElement('td');
                institutionCell.className = 'px-3 py-2 whitespace-nowrap text-sm text-gray-900';
                institutionCell.textContent = inquiry.institution || 'N/A';
                row.appendChild(institutionCell);
                
                // Date
                const dateCell = document.createElement('td');
                dateCell.className = 'px-3 py-2 whitespace-nowrap text-sm text-gray-900';
                dateCell.textContent = inquiry.date || 'N/A';
                row.appendChild(dateCell);
                
                // Time
                const timeCell = document.createElement('td');
                timeCell.className = 'px-3 py-2 whitespace-nowrap text-sm text-gray-900';
                timeCell.textContent = inquiry.time || 'N/A';
                row.appendChild(timeCell);
                
                // Purpose
                const purposeCell = document.createElement('td');
                purposeCell.className = 'px-3 py-2 whitespace-nowrap text-sm text-gray-900';
                
                // Map purpose codes to readable text
                const purposeCode = inquiry.purpose || 'N/A';
                let purposeText = purposeCode;
                
                // Map common purpose codes
                const purposeMap = {
                    '00': 'Account Review',
                    '01': 'Credit Card',
                    '02': 'Housing Loan',
                    '03': 'Vehicle Loan',
                    '04': 'Business Loan',
                    '05': 'Personal Loan',
                    '06': 'Education Loan',
                    '07': 'Loan Against Property',
                    '08': 'Consumer Loan',
                    '09': 'Gold Loan',
                    '10': 'Overdraft',
                    '16': 'Credit Card',
                    '17': 'Employment',
                    '18': 'Insurance',
                    '19': 'Utility',
                    '20': 'Rental',
                    '21': 'Other'
                };
                
                if (purposeMap[purposeCode]) {
                    purposeText = purposeMap[purposeCode];
                }
                
                purposeCell.textContent = purposeText;
                row.appendChild(purposeCell);
                
                tbody.appendChild(row);
            });
            
            table.appendChild(tbody);
            container.appendChild(table);
            
            return container;
        }
        
        /**
         * Creates score factors content
         * @param {array} scoreFactors - The score factors data
         * @return {HTMLElement} The formatted content
         */
        function createScoreFactorsContent(scoreFactors) {
            const container = document.createElement('div');
            container.className = 'space-y-3';
            
            const intro = document.createElement('p');
            intro.className = 'text-sm text-gray-600 mb-4';
            intro.textContent = 'These factors influence your credit score:';
            container.appendChild(intro);
            
            scoreFactors.forEach((factor, index) => {
                const factorEl = document.createElement('div');
                factorEl.className = 'flex items-start space-x-3 p-3 bg-gray-50 rounded';
                
                const numberEl = document.createElement('span');
                numberEl.className = 'flex-shrink-0 w-6 h-6 rounded-full bg-red-500 text-white flex items-center justify-center text-xs font-medium';
                numberEl.textContent = (index + 1);
                
                const textEl = document.createElement('div');
                textEl.className = 'flex-1';
                
                const descriptionEl = document.createElement('p');
                descriptionEl.className = 'text-sm font-medium text-gray-800';
                descriptionEl.textContent = factor.description || 'Unknown factor';
                
                const codeEl = document.createElement('p');
                codeEl.className = 'text-xs text-gray-500';
                codeEl.textContent = 'Code: ' + (factor.code || 'N/A');
                
                textEl.appendChild(descriptionEl);
                textEl.appendChild(codeEl);
                
                factorEl.appendChild(numberEl);
                factorEl.appendChild(textEl);
                
                container.appendChild(factorEl);
            });
            
            return container;
        }
        
        /**
         * Creates other indicators content
         * @param {object} indicators - The other indicators data
         * @return {HTMLElement} The formatted content
         */
        function createOtherIndicatorsContent(indicators) {
            const container = document.createElement('div');
            container.className = 'grid grid-cols-1 md:grid-cols-2 gap-4';
            
            const items = [
                { label: 'Age of Oldest Trade', value: indicators.age_of_oldest_trade + ' months' || 'N/A' },
                { label: 'Number of Open Trades', value: indicators.number_of_open_trades || '0' },
                { label: 'All Lines Ever Written', value: indicators.all_lines_ever_written || '0' },
                { label: 'Lines Written (Last 6 Months)', value: indicators.all_lines_ever_written_in_6_months || '0' },
                { label: 'Lines Written (Last 9 Months)', value: indicators.all_lines_ever_written_in_9_months || '0' }
            ];
            
            items.forEach(item => {
                const itemEl = document.createElement('div');
                itemEl.className = 'flex justify-between border-b pb-2';
                
                const labelEl = document.createElement('span');
                labelEl.className = 'text-gray-600';
                labelEl.textContent = item.label + ':';
                
                const valueEl = document.createElement('span');
                valueEl.className = 'font-medium text-gray-800';
                valueEl.textContent = item.value;
                
                itemEl.appendChild(labelEl);
                itemEl.appendChild(valueEl);
                container.appendChild(itemEl);
            });
            
            return container;
        }

        /**
         * Creates raw API response content
         * @param {string} rawApiResponse - The raw API response as a JSON string
         * @return {HTMLElement} The formatted content
         */
        function createRawApiResponseContent(rawApiResponse) {
            const container = document.createElement('div');
            container.className = 'relative w-full';
            
            // Create a wrapper for better responsiveness
            const wrapper = document.createElement('div');
            wrapper.className = 'w-full overflow-hidden rounded-lg border border-gray-200 shadow-sm';
            
            // Add header with title and copy button
            const header = document.createElement('div');
            header.className = 'flex items-center justify-between bg-gray-100 px-4 py-2 border-b border-gray-200';
            
            const title = document.createElement('h4');
            title.className = 'text-sm font-medium text-gray-700';
            title.textContent = 'API Response JSON';
            
            const buttonGroup = document.createElement('div');
            buttonGroup.className = 'flex space-x-2';
            
            // Add copy button
            const copyButton = document.createElement('button');
            copyButton.className = 'bg-white hover:bg-gray-50 text-gray-700 font-medium py-1 px-3 rounded text-xs border border-gray-300 flex items-center';
            copyButton.innerHTML = '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M7 9a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V9z"></path><path d="M5 3a2 2 0 00-2 2v6a2 2 0 002 2V5h8a2 2 0 00-2-2H5z"></path></svg> Copy';
            copyButton.onclick = function() {
                navigator.clipboard.writeText(rawApiResponse).then(function() {
                    // Change button text temporarily
                    copyButton.innerHTML = '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm9.707 5.707a1 1 0 00-1.414-1.414L9 12.586l-1.293-1.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg> Copied!';
                    setTimeout(function() {
                        copyButton.innerHTML = '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M7 9a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V9z"></path><path d="M5 3a2 2 0 00-2 2v6a2 2 0 002 2V5h8a2 2 0 00-2-2H5z"></path></svg> Copy';
                    }, 2000);
                });
            };
            
            // Add expand/collapse button
            const toggleButton = document.createElement('button');
            toggleButton.className = 'bg-white hover:bg-gray-50 text-gray-700 font-medium py-1 px-3 rounded text-xs border border-gray-300 flex items-center';
            toggleButton.innerHTML = '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg> Expand';
            toggleButton.dataset.expanded = 'false';
            toggleButton.onclick = function() {
                const pre = document.getElementById('apiResponsePre');
                const isExpanded = toggleButton.dataset.expanded === 'true';
                
                if (isExpanded) {
                    pre.classList.remove('max-h-full');
                    pre.classList.add('max-h-96');
                    toggleButton.innerHTML = '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg> Expand';
                    toggleButton.dataset.expanded = 'false';
                } else {
                    pre.classList.remove('max-h-96');
                    pre.classList.add('max-h-full');
                    toggleButton.innerHTML = '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"></path></svg> Collapse';
                    toggleButton.dataset.expanded = 'true';
                }
            };
            
            buttonGroup.appendChild(copyButton);
            buttonGroup.appendChild(toggleButton);
            
            header.appendChild(title);
            header.appendChild(buttonGroup);
            
            // Create a pre element for the JSON content
            const pre = document.createElement('pre');
            pre.id = 'apiResponsePre';
            pre.className = 'bg-gray-50 p-4 text-xs overflow-x-auto max-h-96 overflow-y-auto transition-all duration-300 ease-in-out';
            
            // Try to format the JSON for better readability
            try {
                const jsonObj = JSON.parse(rawApiResponse);
                pre.textContent = JSON.stringify(jsonObj, null, 2);
            } catch (e) {
                // If parsing fails, just use the raw string
                pre.textContent = rawApiResponse;
            }
            
            wrapper.appendChild(header);
            wrapper.appendChild(pre);
            container.appendChild(wrapper);
            
            return container;
        }
        
        function resetPage() {
            resultsSection.classList.add('hidden');
            errorSection.classList.add('hidden');
            formSection.classList.remove('hidden');
            cibilForm.reset();

            // Reset score circle
            scoreCircle.style.setProperty('--stroke-offset', 314);
            scoreCircle.className = 'progress-ring__circle--animated stroke-current'; // Reset color classes
            
            // Reset error message and details
            errorMessageEl.textContent = 'Unable to fetch your report';
            const errorDetailsElement = document.getElementById('errorDetails');
            if (errorDetailsElement) {
                errorDetailsElement.textContent = '';
                errorDetailsElement.classList.add('hidden');
            }
            
            // Remove additional data container if it exists
            const additionalDataContainer = document.getElementById('additionalDataContainer');
            if (additionalDataContainer) {
                additionalDataContainer.remove();
            }
        }
    </script>

</body>
</html>
