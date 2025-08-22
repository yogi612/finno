document.addEventListener('DOMContentLoaded', function() {
    // Initialize all DOM elements
    const elements = {
        form: document.getElementById('cibil-form'),
        results: document.getElementById('cibil-results'),
        loading: document.getElementById('loading-indicator'),
        content: document.getElementById('report-content'),
        error: document.getElementById('error-message'),
        print: document.getElementById('print-report'),
        download: document.getElementById('download-report'),
        fields: {
            name: document.getElementById('name'),
            pan: document.getElementById('panNumber'),
            mobile: document.getElementById('mobileNumber')
        },
        display: {
            score: document.getElementById('cibil-score'),
            date: document.getElementById('report-date'),
            name: document.getElementById('customer-name'),
            pan: document.getElementById('customer-pan'),
            mobile: document.getElementById('customer-mobile'),
            indicator: document.getElementById('score-indicator')
        },
        tables: {
            creditHistory: document.getElementById('credit-history'),
            inquiries: document.getElementById('inquiries')
        }
    };

    // Check if we're on the CIBIL report page
    if (!elements.form || !elements.fields.name || !elements.fields.pan || !elements.fields.mobile) {
        console.log('Not on CIBIL report page or required form fields missing');
        return;
    }
    
    // Form submission
    elements.form.addEventListener('submit', function(e) {
        e.preventDefault();
        fetchCibilReport();
    });
    
    // Fetch CIBIL report function
    function fetchCibilReport(customData) {
        // Show results section with loading indicator
        elements.results.classList.remove('hidden');
        elements.loading.classList.remove('hidden');
        elements.content.classList.add('hidden');
        elements.error.classList.add('hidden');
        
        // Get form data
        let formData;
        if (customData) {
            formData = customData;
        } else {
            formData = {
                name: elements.fields.name.value.trim(),
                panNumber: elements.fields.pan.value.trim(),
                mobileNumber: elements.fields.mobile.value.trim()
            };
        }
        
        // Fetch CIBIL report
        fetch('/api/cibil_lookup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            elements.loading.classList.add('hidden');
            if (data.status !== 'success' || !data.result) {
                elements.error.classList.remove('hidden');
                const errorSpan = elements.error.querySelector('span');
                if (errorSpan) {
                    errorSpan.textContent = data.message || 'Unable to fetch CIBIL report.';
                }
                return;
            }
            elements.content.classList.remove('hidden');
            const result = data.result;
            // Score
            if (elements.display.score) elements.display.score.textContent = result.score || '-';
            // Personal Info (show all fields)
            let personalInfoHtml = '';
            if (result.personalInfo) {
                const pi = result.personalInfo;
                personalInfoHtml += `<div><strong>Name:</strong> ${pi.fullName || pi.name || '-'}</div>`;
                personalInfoHtml += `<div><strong>DOB:</strong> ${pi.dateOfBirth || '-'}</div>`;
                personalInfoHtml += `<div><strong>Gender:</strong> ${pi.gender || '-'}</div>`;
                personalInfoHtml += `<div><strong>Age:</strong> ${pi.age?.age || '-'}</div>`;
                personalInfoHtml += `<div><strong>Income:</strong> ${pi.totalIncome || '-'}</div>`;
                personalInfoHtml += `<div><strong>PAN:</strong> ${(pi.pan || (pi.pANId && pi.pANId[0]?.idNumber) || '-') }</div>`;
                personalInfoHtml += `<div><strong>Email:</strong> ${(pi.emailAddressInfo && pi.emailAddressInfo[0]?.emailAddress) || '-'}</div>`;
                personalInfoHtml += `<div><strong>Phone:</strong> ${(pi.phoneInfo && pi.phoneInfo[0]?.number) || '-'}</div>`;
                if (pi.addressInfo && Array.isArray(pi.addressInfo)) {
                    personalInfoHtml += `<div><strong>Addresses:</strong><ul>`;
                    pi.addressInfo.forEach(addr => {
                        personalInfoHtml += `<li>${addr.address || '-'} (${addr.type || '-'}) [${addr.postal || '-'}]</li>`;
                    });
                    personalInfoHtml += `</ul></div>`;
                }
            }
            // Show in a new div below personal info section
            let personalInfoDiv = document.getElementById('personal-info-extra');
            if (!personalInfoDiv) {
                personalInfoDiv = document.createElement('div');
                personalInfoDiv.id = 'personal-info-extra';
                personalInfoDiv.className = 'mb-4 p-2 bg-gray-50 rounded';
                elements.content.parentNode.insertBefore(personalInfoDiv, elements.content.nextSibling);
            }
            personalInfoDiv.innerHTML = personalInfoHtml;
            // Score indicator
            if (elements.display.indicator) {
                const score = parseInt(result.score) || 0;
                let indicatorText = 'Poor';
                let colorClass = 'bg-red-100 text-red-800';
                if (score >= 750) {
                    indicatorText = 'Excellent';
                    colorClass = 'bg-green-100 text-green-800';
                } else if (score >= 650) {
                    indicatorText = 'Good';
                    colorClass = 'bg-blue-100 text-blue-800';
                } else if (score >= 550) {
                    indicatorText = 'Fair';
                    colorClass = 'bg-yellow-100 text-yellow-800';
                }
                elements.display.indicator.textContent = indicatorText;
                elements.display.indicator.className = `ml-4 px-3 py-1 text-sm font-medium rounded-full ${colorClass}`;
            }
            // Account Summary (show all fields)
            let summaryDiv = document.getElementById('account-summary-extra');
            if (!summaryDiv) {
                summaryDiv = document.createElement('div');
                summaryDiv.id = 'account-summary-extra';
                summaryDiv.className = 'mb-4 p-2 bg-gray-50 rounded';
                elements.content.parentNode.insertBefore(summaryDiv, personalInfoDiv.nextSibling);
            }
            let summaryHtml = '';
            if (result.accountSummary) {
                summaryHtml += '<h4 class="font-semibold mb-2">Account Summary</h4><ul>';
                Object.entries(result.accountSummary).forEach(([key, value]) => {
                    summaryHtml += `<li><strong>${key}:</strong> ${value}</li>`;
                });
                summaryHtml += '</ul>';
            }
            summaryDiv.innerHTML = summaryHtml;
            // Validate and populate tables
            if (!elements.tables.creditHistory || !elements.tables.inquiries) {
                console.error('Credit history or inquiries table not found');
                return;
            }
            
            // Populate credit history
            elements.tables.creditHistory.innerHTML = '';
            const accounts = Array.isArray(result.retailAccountDetails) ? result.retailAccountDetails : [];
            if (accounts.length > 0) {
                accounts.forEach(account => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${account.institution || '-'}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${account.accountType || account.account_type || '-'}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₹${(account.sanctionAmount || account.creditLimit || account.highCredit || account.balance || '-')}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₹${account.balance || account.current_balance || '-'}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${account.accountStatus || account.status || '-'}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${Array.isArray(account.history48Months) ? account.history48Months.map(h => h.paymentStatus).join(', ') : '-'}</td>
                    `;
                    elements.tables.creditHistory.appendChild(row);
                    // Show all fields for each account below the row
                    const detailsRow = document.createElement('tr');
                    let detailsHtml = '<td colspan="6" class="bg-gray-50 px-6 py-2 text-xs text-gray-700">';
                    Object.entries(account).forEach(([key, value]) => {
                        if (Array.isArray(value)) {
                            detailsHtml += `<div><strong>${key}:</strong> <pre>${JSON.stringify(value, null, 2)}</pre></div>`;
                        } else {
                            detailsHtml += `<div><strong>${key}:</strong> ${value}</div>`;
                        }
                    });
                    detailsHtml += '</td>';
                    detailsRow.innerHTML = detailsHtml;
                    elements.tables.creditHistory.appendChild(detailsRow);
                });
            } else {
                const row = document.createElement('tr');
                row.innerHTML = '<td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No credit history available</td>';
                elements.tables.creditHistory.appendChild(row);
            }
            
            // Populate inquiries
            elements.tables.inquiries.innerHTML = '';
            const inquiries = Array.isArray(result.recentEnquiries) ? result.recentEnquiries : [];
            if (inquiries.length > 0) {
                inquiries.forEach(inquiry => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${inquiry.date || inquiry.dateReported || '-'}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${inquiry.institution || '-'}</td>
                    `;
                    elements.tables.inquiries.appendChild(row);
                    // Show all fields for each enquiry below the row
                    const detailsRow = document.createElement('tr');
                    let detailsHtml = '<td colspan="2" class="bg-gray-50 px-6 py-2 text-xs text-gray-700">';
                    Object.entries(inquiry).forEach(([key, value]) => {
                        if (Array.isArray(value)) {
                            detailsHtml += `<div><strong>${key}:</strong> <pre>${JSON.stringify(value, null, 2)}</pre></div>`;
                        } else {
                            detailsHtml += `<div><strong>${key}:</strong> ${value}</div>`;
                        }
                    });
                    detailsHtml += '</td>';
                    detailsRow.innerHTML = detailsHtml;
                    elements.tables.inquiries.appendChild(detailsRow);
                });
            } else {
                const row = document.createElement('tr');
                row.innerHTML = '<td colspan="2" class="px-6 py-4 text-center text-sm text-gray-500">No recent inquiries available</td>';
                elements.tables.inquiries.appendChild(row);
            }
        })
        .catch(error => {
            elements.loading.classList.add('hidden');
            elements.error.classList.remove('hidden');
            console.error('Error fetching CIBIL report:', error);
        });
    }
    
    // Print and download report event listeners
    if (elements.print) {
        elements.print.addEventListener('click', () => window.print());
    }
    
    if (elements.download) {
        elements.download.addEventListener('click', () => {
            alert('PDF download functionality will be implemented here');
            // This would typically use a library like jsPDF or call a server endpoint
            // that generates a PDF from the report data
        });
    }
    
    // Fetch again buttons
    const fetchAgainButtons = document.querySelectorAll('.fetch-again');
    fetchAgainButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (!elements.fields.name || !elements.fields.pan || !elements.fields.mobile) {
                console.error('Form fields not found');
                return;
            }
            
            // Set form field values from button attributes
            elements.fields.name.value = this.getAttribute('data-name') || '';
            elements.fields.pan.value = this.getAttribute('data-pan') || '';
            elements.fields.mobile.value = this.getAttribute('data-mobile') || '';
            
            // Fetch report
            fetchCibilReport();
            
            // Scroll to results
            if (elements.results) {
                elements.results.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
}); // End of DOMContentLoaded event listener