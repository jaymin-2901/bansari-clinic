import { z } from 'zod';

// ─── Basic Info Validation ─────────────────────────────

export const basicInfoSchema = z.object({
  fullName: z.string().min(2, 'Name is required').max(100),
  mobile: z
    .string()
    .regex(/^[6-9]\d{9}$/, 'Enter valid 10-digit mobile number'),
  age: z.coerce.number().min(0, 'Age must be 0+').max(120, 'Age must be ≤ 120'),
  gender: z.enum(['MALE', 'FEMALE', 'OTHER']),
  city: z.string().min(2, 'City is required').max(100),
  appointmentDate: z.string().min(1, 'Select appointment date'),
  consultationType: z.enum(['OFFLINE', 'ONLINE']),
});

export type BasicInfoSchema = z.infer<typeof basicInfoSchema>;

// ─── Offline (Short Pre-Visit) Form ───────────────────

export const offlineFormSchema = z.object({
  chiefComplaint: z.string().min(3, 'Describe your complaint'),
  complaintDuration: z.string().min(1, 'How long have you had this?'),
  medicalHistory: z.array(z.string()),
  currentMedicines: z.string().optional().default(''),
  hasAllergy: z.boolean(),
  allergyDetails: z.string().optional().default(''),
  clinicConfirmation: z.literal(true, 'Please confirm to proceed'),
});

export type OfflineFormSchema = z.infer<typeof offlineFormSchema>;

// ─── Complaint Entry (Online) ─────────────────────────

export const complaintSchema = z.object({
  affectedArea: z.string().min(1, 'Select affected area'),
  symptoms: z.string().min(3, 'Describe symptoms'),
  sinceWhen: z.string().min(1, 'Required'),
  frequency: z.string().optional().default(''),
  worseBy: z.string().optional().default(''),
  betterBy: z.string().optional().default(''),
  associatedSymptoms: z.string().optional().default(''),
});

// ─── Past Disease Entry ───────────────────────────────

export const pastDiseaseSchema = z.object({
  category: z.string(),
  diseaseName: z.string(),
  treatmentTaken: z.string().optional().default(''),
  yearsAgo: z.coerce.number().nullable().optional(),
  duration: z.string().optional().default(''),
  residualSymptoms: z.string().optional().default(''),
});

// ─── Family History Entry ─────────────────────────────

export const familyHistorySchema = z.object({
  relation: z.string().min(1),
  disease: z.string().optional().default(''),
  ageOfOnset: z.coerce.number().nullable().optional(),
  causeOfDeath: z.string().optional().default(''),
  isAlive: z.boolean().default(true),
});

// ─── Physical Generals ────────────────────────────────

export const physicalGeneralsSchema = z.object({
  appetite: z.string().optional().default(''),
  thirst: z.string().optional().default(''),
  foodCravings: z.string().optional().default(''),
  foodAversions: z.string().optional().default(''),
  stool: z.string().optional().default(''),
  urine: z.string().optional().default(''),
  sweat: z.string().optional().default(''),
  sleep: z.string().optional().default(''),
  dreams: z.string().optional().default(''),
  thermalWinter: z.string().optional().default(''),
  thermalSummer: z.string().optional().default(''),
  thermalMonsoon: z.string().optional().default(''),
  menstrualHistory: z.string().optional().default(''),
});

// ─── Mental Profile ───────────────────────────────────

export const mentalProfileSchema = z.object({
  nature: z.string().optional().default(''),
  lifeGoal: z.string().optional().default(''),
  anxietyTriggers: z.string().optional().default(''),
  fears: z.string().optional().default(''),
  angerExpression: z.string().optional().default(''),
  sadnessTriggers: z.string().optional().default(''),
  memory: z.string().optional().default(''),
  hobbies: z.string().optional().default(''),
  saddestEvent: z.string().optional().default(''),
  happiestEvent: z.string().optional().default(''),
  unforgettableIncident: z.string().optional().default(''),
  revengeTendency: z.string().optional().default(''),
  cryingNature: z.string().optional().default(''),
  aloneVsCompany: z.string().optional().default(''),
  relationshipImpact: z.string().optional().default(''),
  mentalEffectOfDisease: z.string().optional().default(''),
  socialComfort: z.string().optional().default(''),
  personalityTraits: z.array(z.string()).default([]),
});

// ─── Declaration ──────────────────────────────────────

export const declarationSchema = z.object({
  signature: z.string().min(1, 'Digital signature is required'),
  truthConfirmation: z.literal(true, 'You must confirm this is true'),
});

// ─── Full Online Intake ───────────────────────────────

export const onlineIntakeSchema = z.object({
  complaints: z.array(complaintSchema).min(1, 'Add at least one complaint'),
  pastDiseases: z.array(pastDiseaseSchema).default([]),
  familyHistory: z.array(familyHistorySchema).default([]),
  physicalGenerals: physicalGeneralsSchema,
  mentalProfile: mentalProfileSchema,
  declaration: declarationSchema,
});

export type OnlineIntakeSchema = z.infer<typeof onlineIntakeSchema>;

// ─── Auth Schemas ─────────────────────────────────────

export const loginSchema = z.object({
  mobile: z.string().regex(/^[6-9]\d{9}$/, 'Enter valid mobile number'),
  password: z.string().min(6, 'Password must be at least 6 characters'),
});

export const signupSchema = z.object({
  fullName: z.string().min(2, 'Name is required'),
  mobile: z.string().regex(/^[6-9]\d{9}$/, 'Enter valid mobile number'),
  email: z.string().email().optional().or(z.literal('')),
  password: z.string().min(6, 'Minimum 6 characters'),
  confirmPassword: z.string(),
}).refine((d) => d.password === d.confirmPassword, {
  message: 'Passwords do not match',
  path: ['confirmPassword'],
});

// ─── Prescription Schema ──────────────────────────────

export const prescriptionSchema = z.object({
  medicineName: z.string().min(1, 'Medicine name required'),
  potency: z.string().optional().default(''),
  dosage: z.string().optional().default(''),
  frequency: z.string().optional().default(''),
  duration: z.string().optional().default(''),
  instructions: z.string().optional().default(''),
});
