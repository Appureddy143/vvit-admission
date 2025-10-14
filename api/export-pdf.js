import { sql } from '@vercel/postgres';
import { PDFDocument, rgb, StandardFonts } from 'pdf-lib';

export default async function handler(request, response) {
  try {
    // Fetch all student data from the database
    const { rows } = await sql`SELECT * FROM students ORDER BY student_id_text ASC;`;

    // Create a new PDF document
    const pdfDoc = await PDFDocument.create();
    const font = await pdfDoc.embedFont(StandardFonts.Helvetica);
    const boldFont = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
    
    let page = pdfDoc.addPage();
    const { width, height } = page.getSize();
    let y = height - 50;
    const lineHeight = 14;
    const margin = 50;

    // Add a title to the first page
    page.drawText('VVIT Student Admission Data', {
        x: margin,
        y: y,
        font: boldFont,
        size: 18,
    });
    y -= 40;

    // Loop through each student and add their data to the PDF
    for (const student of rows) {
        // Add a new page if the current one is full
        if (y < margin + 150) { // Check if there's enough space for the next record
            page = pdfDoc.addPage();
            y = height - 50;
        }

        const drawLine = (label, value) => {
            if (!value || y < margin) return;
            page.drawText(`${label}:`, { x: margin, y: y, font: boldFont, size: 10 });
            page.drawText(String(value), { x: margin + 150, y: y, font: font, size: 10, maxWidth: width - margin * 2 - 150 });
            y -= lineHeight;
        };

        drawLine('Student ID', student.student_id_text);
        drawLine('Name', student.student_name);
        drawLine('Date of Birth', new Date(student.dob).toLocaleDateString());
        drawLine('Father\'s Name', student.father_name);
        drawLine('Email', student.email);
        drawLine('Mobile Number', student.mobile_number);
        drawLine('Admission Through', student.admission_through);
        drawLine('Allotted Branch', student.allotted_branch_kea || student.allotted_branch_management);
        
        // Add a separator line
        y -= 10;
        page.drawLine({
            start: { x: margin, y: y },
            end: { x: width - margin, y: y },
            thickness: 0.5,
            color: rgb(0.5, 0.5, 0.5),
        });
        y -= 20;
    }

    // Save the PDF to a buffer
    const pdfBytes = await pdfDoc.save();

    // Set headers to trigger a download
    response.setHeader('Content-Disposition', 'attachment; filename="admissions_export.pdf"');
    response.setHeader('Content-Type', 'application/pdf');
    response.status(200).send(Buffer.from(pdfBytes));

  } catch (error) {
    console.error('--- PDF EXPORT ERROR ---', error);
    response.status(500).json({ error: 'Failed to export PDF data' });
  }
}
