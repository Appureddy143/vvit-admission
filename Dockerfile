import { sql } from '@vercel/postgres';
import xlsx from 'xlsx';

export default async function handler(request, response) {
  try {
    // Fetch all student data from the database
    const { rows } = await sql`SELECT * FROM students ORDER BY id ASC;`;

    // Create a new workbook and a worksheet
    const workbook = xlsx.utils.book_new();
    const worksheet = xlsx.utils.json_to_sheet(rows);

    // Append the worksheet to the workbook
    xlsx.utils.book_append_sheet(workbook, worksheet, 'Admissions');

    // Generate the Excel file buffer
    const buffer = xlsx.write(workbook, { type: 'buffer', bookType: 'xlsx' });

    // Set headers to trigger a download
    response.setHeader('Content-Disposition', 'attachment; filename="admissions_export.xlsx"');
    response.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    response.status(200).send(buffer);

  } catch (error) {
    console.error('--- EXCEL EXPORT ERROR ---', error);
    response.status(500).json({ error: 'Failed to export data' });
  }
}
