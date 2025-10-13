import { google } from 'googleapis';
import { formidable } from 'formidable';
import fs from 'fs';
import fetch from 'node-fetch';
import { PDFDocument, rgb, StandardFonts } from 'pdf-lib';

export const config = { api: { bodyParser: false } };

export default async function handler(req, res) {
    if (req.method !== 'POST') return res.status(405).json({ error: 'Method Not Allowed' });

    try {
        // --- Google Auth ---
        const auth = new google.auth.GoogleAuth({
            credentials: {
                client_email: process.env.GOOGLE_CLIENT_EMAIL,
                private_key: process.env.GOOGLE_PRIVATE_KEY.replace(/\\n/g, '\n'),
            },
            scopes: ['https://www.googleapis.com/auth/spreadsheets', 'https://www.googleapis.com/auth/drive'],
        });

        const sheets = google.sheets({ version: 'v4', auth });
        const drive = google.drive({ version: 'v3', auth });

        // --- Parse form ---
        const { fields, files } = await parseForm(req);

        // --- Generate Student ID ---
        const studentId = await generateStudentIdFromSheet(sheets);

        // --- Create Drive folder ---
        const studentFolderId = await createStudentFolder(drive, studentId, fields.student_name);

        // --- Upload files to Drive ---
        const fileLinks = await uploadFilesToDrive(drive, files, studentFolderId);

        // --- Save data to Sheet ---
        await saveDataToSheet(sheets, { ...fields, ...fileLinks, student_id: studentId });

        // --- Generate PDF ---
        const pdfBytes = await createAdmissionPDF({ ...fields, student_id: studentId, ...fileLinks });
        const pdfFilePath = `/tmp/${studentId}.pdf`;
        fs.writeFileSync(pdfFilePath, pdfBytes);

        // --- Upload PDF to Drive ---
        const pdfMetadata = { name: `${studentId}_admission.pdf`, parents: [studentFolderId] };
        const media = { mimeType: 'application/pdf', body: fs.createReadStream(pdfFilePath) };
        const pdfDriveFile = await drive.files.create({ resource: pdfMetadata, media: media, fields: 'id, webViewLink' });

        const pdfUrl = pdfDriveFile.data.webViewLink;

        return res.status(200).json({ message: 'Success', studentId, pdfUrl });

    } catch (error) {
        console.error(error);
        return res.status(500).json({ error: 'Internal Server Error' });
    }
}

// --- Helpers ---
async function parseForm(req) {
    const form = formidable({});
    const [fields, files] = await form.parse(req);
    const singleValueFields = Object.fromEntries(Object.entries(fields).map(([k,v]) => [k, v[0]]));
    return { fields: singleValueFields, files };
}

async function generateStudentIdFromSheet(sheets) {
    const timeResponse = await fetch('http://worldtimeapi.org/api/timezone/Asia/Kolkata');
    const timeData = await timeResponse.json();
    const year = new Date(timeData.utc_datetime).getFullYear().toString().slice(-2);
    const prefix = `1VJ${year}`;

    const res = await sheets.spreadsheets.values.get({ spreadsheetId: process.env.GOOGLE_SHEET_ID, range: 'Sheet1!A:A' });
    const lastId = res.data.values?.[res.data.values.length - 1]?.[0] || null;
    const newSerial = lastId?.startsWith(prefix) ? parseInt(lastId.slice(prefix.length)) + 1 : 1;
    return `${prefix}${String(newSerial).padStart(3, '0')}`;
}

async function createStudentFolder(drive, studentId, studentName) {
    const folderMetadata = { name: `${studentId} - ${studentName}`, parents: [process.env.GOOGLE_DRIVE_FOLDER_ID], mimeType: 'application/vnd.google-apps.folder' };
    const folder = await drive.files.create({ resource: folderMetadata, fields: 'id' });
    return folder.data.id;
}

async function uploadFilesToDrive(drive, files, parentFolderId) {
    const links = {};
    for (const key in files) {
        const file = files[key][0];
        if (!file) continue;
        const fileMetadata = { name: file.originalFilename, parents: [parentFolderId] };
        const media = { mimeType: file.mimetype, body: fs.createReadStream(file.filepath) };
        const uploaded = await drive.files.create({ resource: fileMetadata, media, fields: 'id, webViewLink' });
        links[`${key}_url`] = uploaded.data.webViewLink;
    }
    return links;
}

async function saveDataToSheet(sheets, data) {
    const headers = ['student_id','student_name','dob','father_name','mother_name','mobile_number','parent_mobile_number','email','previous_college','previous_combination','permanent_address','category','sub_caste','admission_through','cet_number','seat_allotted','allotted_branch_kea','allotted_branch_management','cet_rank','photo_url','marks_card_url','aadhaar_front_url','aadhaar_back_url','caste_income_url','submission_date'];
    const row = headers.map(h => data[h] || '');
    row[headers.indexOf('submission_date')] = new Date().toISOString();
    await sheets.spreadsheets.values.append({ spreadsheetId: process.env.GOOGLE_SHEET_ID, range: 'Sheet1', valueInputOption: 'USER_ENTERED', resource: { values: [row] } });
}

async function createAdmissionPDF(data) {
    const pdfDoc = await PDFDocument.create();
    const page = pdfDoc.addPage([400, 600]);
    const font = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
    page.drawText(`Admission Slip`, { x: 120, y: 550, size: 20, font, color: rgb(0.9,0,0) });
    page.drawText(`Student ID: ${data.student_id}`, { x: 20, y: 500, size: 14 });
    page.drawText(`Name: ${data.student_name}`, { x: 20, y: 480, size: 14 });
    page.drawText(`DOB: ${data.dob}`, { x: 20, y: 460, size: 14 });
    page.drawText(`Mobile: ${data.mobile_number}`, { x: 20, y: 440, size: 14 });
    page.drawText(`Admission Through: ${data.admission_through}`, { x: 20, y: 420, size: 14 });
    page.drawText(`Allotted Branch: ${data.allotted_branch_kea || data.allotted_branch_management}`, { x: 20, y: 400, size: 14 });
    page.drawText(`Submission Date: ${new Date().toLocaleDateString()}`, { x: 20, y: 380, size: 14 });
    return await pdfDoc.save();
}
