/**
 * Ticket Printer Utility for Servimedic
 * Generates a thermal-printer friendly receipt and triggers the print dialog.
 */

function printTicket(data) {
    // Default header information
    const headerInfo = {
        title: 'Servimedic',
        address: '8va calle 10-21 zona 5 huehuetenango',
        phone: '3404 9600'
    };

    // Construct the ticket HTML
    let ticketContent = `
        <html>
        <head>
            <title>Ticket ${data.ticketNumber || ''}</title>
            <style>
                @page {
                    margin: 0;
                    size: 80mm auto; /* Auto height */
                }
                body {
                    font-family: 'Courier New', Courier, monospace;
                    font-size: 12px;
                    width: 72mm; /* Slightly less than 80mm to prevent overflow */
                    margin: 0 auto;
                    padding: 5px 0;
                    color: #000;
                    background: #fff;
                }
                .header {
                    text-align: center;
                    margin-bottom: 10px;
                    border-bottom: 1px dashed #000;
                    padding-bottom: 5px;
                }
                .header h2 {
                    margin: 0;
                    font-size: 16px;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                .header p {
                    margin: 2px 0;
                    font-size: 12px;
                }
                .info {
                    margin-bottom: 10px;
                }
                .info p {
                    margin: 2px 0;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 10px;
                }
                th {
                    text-align: left;
                    border-bottom: 1px solid #000;
                    font-size: 11px;
                    padding: 2px 0;
                }
                td {
                    padding: 4px 0;
                    vertical-align: top;
                    font-size: 12px;
                }
                .qty { width: 15%; text-align: center; }
                .desc { width: 55%; }
                .price { width: 30%; text-align: right; }
                
                .totals {
                    text-align: right;
                    border-top: 1px dashed #000;
                    padding-top: 5px;
                    margin-top: 5px;
                }
                .totals p {
                    margin: 2px 0;
                    font-weight: bold;
                    font-size: 14px;
                }
                .footer {
                    text-align: center;
                    margin-top: 15px;
                    font-size: 10px;
                    border-top: 1px solid #eee;
                    padding-top: 5px;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>${headerInfo.title}</h2>
                <p>${headerInfo.address}</p>
                <p>Tel: ${headerInfo.phone}</p>
            </div>
            
            <div class="info">
                <p><strong>Ticket:</strong> #${data.ticketNumber || '---'}</p>
                <p><strong>Fecha:</strong> ${data.date || new Date().toLocaleString()}</p>
                <p><strong>Paciente:</strong> ${data.patientName || 'Consumidor Final'}</p>
                ${data.doctorName ? `<p><strong>Dr(a):</strong> ${data.doctorName}</p>` : ''}
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="qty">Cant</th>
                        <th class="desc">Desc</th>
                        <th class="price">Total</th>
                    </tr>
                </thead>
                <tbody>
    `;

    // Add items
    if (data.items && data.items.length > 0) {
        data.items.forEach(item => {
            ticketContent += `
                <tr>
                    <td class="qty">${item.quantity}</td>
                    <td class="desc">${item.description}</td>
                    <td class="price">Q${parseFloat(item.total).toFixed(2)}</td>
                </tr>
            `;
        });
    } else {
        ticketContent += `
            <tr>
                <td colspan="3" style="text-align:center;">- Sin detalles -</td>
            </tr>
        `;
    }

    ticketContent += `
                </tbody>
            </table>

            <div class="totals">
                <p>Total: Q${parseFloat(data.total).toFixed(2)}</p>
            </div>

            <div class="footer">
                <p>¡Gracias por su preferencia!</p>
                <p>Servimedic - Salud y Bienestar</p>
            </div>
        </body>
        </html>
    `;

    // Open window for PREVIEW/SIMULATION (requested by user)
    const width = 350;
    const height = 600;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;

    // We use a unique name to force a new window
    const printWindow = window.open('', 'TicketPreview', `width=${width},height=${height},top=${top},left=${left},resizable=yes,scrollbars=yes`);

    if (printWindow) {
        printWindow.document.open();
        // Explicitly set DOCTYPE to force standard mode
        printWindow.document.write('<!DOCTYPE html>');
        printWindow.document.write(ticketContent);
        printWindow.document.close();
        printWindow.focus();

        // SIMULATION MODE: 
        // We do NOT call print() automatically so the user can verify the HTML content.
        console.log("Ticket Preview Mode Active");

        // Uncomment the lines below to enable auto-printing once driver is fixed:
        /*
        setTimeout(() => {
             printWindow.print();
             // printWindow.close();
        }, 500);
        */

    } else {
        alert('Por favor permita las ventanas emergentes para ver el ticket.');
    }
}
