import { google } from 'googleapis';
import { formidable } from 'formidable';
import fs from 'fs';
import fetch from 'node-fetch';
import { PDFDocument, rgb, StandardFonts } from 'pdf-lib';

export const config = { api: { bodyParser: false } };

export default async function handler(request, response) {
  if (request.method !== 'POST') {
    return response.status(405).json({ error: 'Method Not Allowed' });
  }

  try {
    const auth = new google.auth.GoogleAuth({
        credentials: {
            client_email: process.env.GOOGLE_CLIENT_EMAIL,
            private_key: process.env.GOOGLE_PRIVATE_KEY.replace(/\\n/g, '\n'),
        },
        scopes: ['https://www.googleapis.com/auth/spreadsheets', 'https://www.googleapis.com/auth/drive'],
    });

    const sheets = google.sheets({ version: 'v4', auth });
    const drive = google.drive({ version: 'v3', auth });
    
    const { fields, files } = await parseForm(request);
    const studentId = await generateStudentIdFromSheet(sheets);
    const studentFolderId = await createStudentFolder(drive, studentId, fields.student_name);
    const fileLinksAndIds = await uploadFilesToDrive(drive, files, studentFolderId);
    await saveDataToSheet(sheets, { ...fields, ...fileLinksAndIds, student_id: studentId });
    const pdfBytes = await generatePdf({ ...fields, student_id: studentId }, drive, fileLinksAndIds.photo_id);
    const pdfBase64 = Buffer.from(pdfBytes).toString('base64');

    return response.status(200).json({
        message: 'Application submitted successfully!',
        studentId: studentId,
        pdfData: pdfBase64
    });

  } catch (error) {
    console.error('--- API ERROR ---', error.message, error.stack);
    return response.status(500).json({ error: 'Internal Server Error', details: error.message });
  }
}

// --- Helper Functions ---

async function parseForm(request) {
    const form = formidable({});
    const [fields, files] = await form.parse(request);
    const singleValueFields = Object.fromEntries(Object.entries(fields).map(([key, value]) => [key, value[0]]));
    return { fields: singleValueFields, files };
}

async function generateStudentIdFromSheet(sheets) {
    const timeResponse = await fetch('http://worldtimeapi.org/api/timezone/Asia/Kolkata');
    if (!timeResponse.ok) throw new Error('Failed to fetch current time');
    const timeData = await timeResponse.json();
    const year = new Date(timeData.utc_datetime).getFullYear().toString().slice(-2);
    const prefix = `1VJ${year}`;

    const res = await sheets.spreadsheets.values.get({
        spreadsheetId: process.env.GOOGLE_SHEET_ID,
        range: 'Sheet1!A:A',
    });

    const lastId = res.data.values ? res.data.values[res.data.values.length - 1][0] : null;
    let newSerial = 1;
    if (lastId && lastId.startsWith(prefix)) {
        newSerial = parseInt(lastId.substring(prefix.length)) + 1;
    }
    return `${prefix}${String(newSerial).padStart(3, '0')}`;
}

// --- UPDATED FUNCTION ---
async function createStudentFolder(drive, studentId, studentName) {
    // Step 1: Create the folder.
    const folderMetadata = {
        name: `${studentId} - ${studentName}`,
        parents: [process.env.GOOGLE_DRIVE_FOLDER_ID],
        mimeType: 'application/vnd.google-apps.folder',
    };
    const folder = await drive.files.create({
        resource: folderMetadata,
        fields: 'id',
    });
    const folderId = folder.data.id;

    // Step 2: Transfer ownership to the user.
    await drive.permissions.create({
        fileId: folderId,
        requestBody: {
            role: 'owner',
            type: 'user',
            emailAddress: process.env.USER_EMAIL_ADDRESS,
        },
        transferOwnership: true, 
    });
    
    // Step 3: Add a short delay to prevent a race condition.
    await new Promise(resolve => setTimeout(resolve, 2000)); // 2-second pause

    return folderId;
}

async function uploadFilesToDrive(drive, files, parentFolderId) {
    const linksAndIds = {};
    for (const key in files) {
        const file = files[key][0];
        if (file) {
            const fileMetadata = { name: file.originalFilename, parents: [parentFolderId] };
            const media = { mimeType: file.mimetype, body: fs.createReadStream(file.filepath) };
            const driveFile = await drive.files.create({
                resource: fileMetadata, media: media, fields: 'id, webViewLink',
            });
            linksAndIds[`${key}_url`] = driveFile.data.webViewLink;
            linksAndIds[`${key}_id`] = driveFile.data.id;
        }
    }
    return linksAndIds;
}

async function saveDataToSheet(sheets, data) {
    const headers = [
        'student_id', 'student_name', 'dob', 'father_name', 'mother_name', 
        'mobile_number', 'parent_mobile_number', 'email', 'permanent_address', 
        'previous_college', 'previous_combination', 'category', 'sub_caste', 
        'admission_through', 'cet_number', 'seat_allotted', 'allotted_branch_kea', 
        'allotted_branch_management', 'cet_rank', 'photo_url', 'marks_card_url', 
        'aadhaar_front_url', 'aadhaar_back_url', 'caste_income_url', 'submission_date'
    ];
    const row = headers.map(header => data[header] || '');
    row[headers.indexOf('submission_date')] = new Date().toISOString();
    await sheets.spreadsheets.values.append({
        spreadsheetId: process.env.GOOGLE_SHEET_ID,
        range: 'Sheet1',
        valueInputOption: 'USER_ENTERED',
        resource: { values: [row] },
    });
}

async function generatePdf(data, drive, photoFileId) {
    const pdfDoc = await PDFDocument.create();
    const page = pdfDoc.addPage();
    const { width, height } = page.getSize();
    const font = await pdfDoc.embedFont(StandardFonts.TimesRoman);
    const boldFont = await pdfDoc.embedFont(StandardFonts.TimesRomanBold);
    const year = new Date().getFullYear();
    const nextYear = new Date().getFullYear() + 1;

    if (photoFileId) {
        try {
            const photoRes = await drive.files.get({ 
                fileId: photoFileId, 
                alt: 'media',
            }, { responseType: 'arraybuffer' });
            const photoBytes = new Uint8Array(photoRes.data);
            const photoImage = await pdfDoc.embedJpg(photoBytes);
            page.drawImage(photoImage, { x: 50, y: height - 150, width: 100, height: 100 });
        } catch (e) {
            console.error("Could not embed photo in PDF:", e.message);
        }
    }

    page.drawText('Vijay Vittal Institute of Technology', { x: 170, y: height - 80, font: boldFont, size: 20 });
    page.drawText(`Student ID: ${data.student_id}`, { x: 400, y: height - 120, font: boldFont, size: 12 });

    let yPos = height - 180;
    const drawLine = (label, value) => {
        if (!value) return;
        page.drawText(`${label}:`, { x: 60, y: yPos, font: boldFont, size: 12 });
        page.drawText(String(value).toUpperCase(), { x: 200, y: yPos, font: font, size: 12 });
        yPos -= 20;
    };
    drawLine('Student Name', data.student_name);
    drawLine('Date of Birth', data.dob);
    drawLine('Father Name', data.father_name);
    drawLine('Allotted Branch', data.allotted_branch_kea || data.allotted_branch_management);
    drawLine('Admission Year', `${year}-${nextYear.toString().slice(-2)}`);
    drawLine('Admission Through', data.admission_through);
    drawLine('Category', data.category);

    yPos -= 30;
    page.drawLine({ start: { x: 50, y: yPos }, end: { x: width - 50, y: yPos }, thickness: 1 });
    yPos -= 20;
    page.drawText('Documents Submitted (Student Copy)', { x: 180, y: yPos, font: boldFont, size: 14 });
    yPos -= 30;

    const receiptItems = [
        '1. Previous Marks Cards', '2. Transfer Certificate (Original)',
        '3. Study Certificate (Original)', '4. Caste & Income Certificate',
        '5. Passport Size Photo'
    ];
    receiptItems.forEach(item => {
        page.drawText(item, { x: 80, y: yPos, font: font, size: 12 });
        page.drawText('Date: ____________', { x: 350, y: yPos, font: font, size: 12 });
        yPos -= 25;
    });

     yPos -= 40;
    page.drawLine({ start: { x: 50, y: yPos }, end: { x: width - 50, y: yPos }, thickness: 1, dashArray: [5, 5] });
    yPos -= 20;
    page.drawText('Documents Submitted (College Copy)', { x: 180, y: yPos, font: boldFont, size: 14 });
    yPos -= 30;
    receiptItems.forEach(item => {
        page.drawText(item, { x: 80, y: yPos, font: font, size: 12 });
        page.drawText('Date: ____________', { x: 350, y: yPos, font: font, size: 12 });
        yPos -= 25;
    });

    return pdfDoc.save();
}

