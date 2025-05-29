<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\NextOfKin;
use App\Models\LocalDoctor;
use App\Models\PatientAppointment;
use App\Models\Staff;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function create()
    {
        $staff = Staff::all();
        $localDoctors = LocalDoctor::all();
        return view('patients.create', compact('staff', 'localDoctors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            // Patient
            'fname' => 'required',
            'lname' => 'required',
            'patienttype' => 'required|in:inpatient,outpatient',
            'address' => 'required',
            'phone' => 'nullable|string|max:20',
            'dateofbirth' => 'required|date',
            'sex' => 'required|in:M,F',
            'maritalstatus' => 'required',
            'dateregistered' => 'required|date',

            // Next of Kin
            'kin_name' => 'required',
            'kin_relationship' => 'required',
            'kin_address' => 'required',
            'kin_phone' => 'required',

            // Doctor
            'clinicID' => 'required|exists:local_doctors,clinicID',

            // Appointment
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'examination_room' => 'required',
            'staffID' => 'required|exists:staff,staffID',
        ]);

        // 1. Save Patient
        $patient = Patient::create([
            'fname' => $request->fname,
            'lname' => $request->lname,
            'patienttype' => $request->patienttype,
            'address' => $request->address,
            'phone' => $request->phone,
            'dateofbirth' => $request->dateofbirth,
            'sex' => $request->sex,
            'maritalstatus' => $request->maritalstatus,
            'dateregistered' => $request->dateregistered,
            'clinicID' => $request->clinicID
        ]);

        // 2. Save Next of Kin
        NextOfKin::create([
            'patientID' => $patient->patientID,
            'name' => $request->kin_name,
            'relationship' => $request->kin_relationship,
            'address' => $request->kin_address,
            'phone' => $request->kin_phone
        ]);

        // 3. Save Appointment
        PatientAppointment::create([
            'patientID' => $patient->patientID,
            'staffID' => $request->staffID,
            'appointmentDate' => $request->appointment_date,
            'appointmentTime' => $request->appointment_time,
            'examinationRoom' => $request->examination_room,
            'appointmentOutcome' => $request->appointment_outcome ?? null,
        ]);

        return redirect()->route('patients.index')->with('success', 'Patient registered successfully.');
    }

    public function index(Request $request)
    {
        $query = Patient::with('kin'); // eager load next of kin

        // Search by first or last name
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('fname', 'like', "%{$search}%")
                ->orWhere('lname', 'like', "%{$search}%");
            });
        }

        // Sort by fname or lname
        if (in_array($request->input('sort'), ['fname', 'lname'])) {
            $query->orderBy($request->input('sort'), 'asc');
        } else {
            $query->orderBy('fname', 'asc'); // default sort
        }

        $patients = $query->paginate(10)->withQueryString();

        return view('patients.index', compact('patients'));
    }


    public function edit($id)
    {
        $patient = Patient::with('kin')->findOrFail($id);
        $localDoctors = LocalDoctor::all();
        return view('patients.edit', compact('patient', 'localDoctors'));
    }


    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'fname' => 'required|string|max:255',
            'lname' => 'required|string|max:255',
            'patienttype' => 'required|in:inpatient,outpatient',
            'address' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'dateofbirth' => 'required|date',
            'sex' => 'required|in:M,F',
            'maritalstatus' => 'required|string|max:50',
            'dateregistered' => 'required|date',
            'clinicID' => 'nullable|exists:local_doctors,clinicID',

            // Add validation for kin fields here if you want to edit them
            'kin_name' => 'required|string|max:255',
            'kin_relationship' => 'required|string|max:255',
            'kin_address' => 'required|string|max:255',
            'kin_phone' => 'required|string|max:20',
        ]);

        $patient = Patient::findOrFail($id);
        $patient->update($validated);

        // Update or create kin info (assuming one kin)
        $kin = $patient->kin->first();
        if ($kin) {
            $kin->update([
                'name' => $request->kin_name,
                'relationship' => $request->kin_relationship,
                'address' => $request->kin_address,
                'phone' => $request->kin_phone,
            ]);
        } else {
            // create kin if none exists
            NextOfKin::create([
                'patientID' => $patient->patientID,
                'name' => $request->kin_name,
                'relationship' => $request->kin_relationship,
                'address' => $request->kin_address,
                'phone' => $request->kin_phone,
            ]);
        }

        return redirect()->route('patients.index')->with('success', 'Patient updated successfully.');
    }


    public function destroy($id)
    {
        $patient = Patient::findOrFail($id);
        $patient->delete();

        return redirect()->route('patients.index')->with('success', 'Patient deleted successfully.');
    }
}
