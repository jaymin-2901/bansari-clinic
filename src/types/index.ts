// ─── TypeScript Type Definitions ───────────────────────

export type Lang = 'en' | 'gu';

export type ConsultationType = 'OFFLINE' | 'ONLINE';
export type FormType = 'SHORT' | 'FULL';
export type Gender = 'MALE' | 'FEMALE' | 'OTHER';
export type Role = 'ADMIN' | 'PATIENT';

export type AppointmentStatus =
  | 'PENDING'
  | 'CONFIRMED'
  | 'IN_PROGRESS'
  | 'COMPLETED'
  | 'CANCELLED'
  | 'NO_SHOW';

// ─── Basic Info (Step 1 — Common) ──────────────────────

export interface BasicInfoData {
  fullName: string;
  mobile: string;
  age: number;
  gender: Gender;
  city: string;
  appointmentDate: string;
  consultationType: ConsultationType;
}

// ─── Offline (Short Form) ──────────────────────────────

export interface OfflineFormData {
  chiefComplaint: string;
  complaintDuration: string;
  medicalHistory: string[];
  currentMedicines: string;
  hasAllergy: boolean;
  allergyDetails: string;
  clinicConfirmation: boolean;
  reports?: File[];
}

// ─── Online Full Form Types ────────────────────────────

export interface ComplaintEntry {
  id?: string;
  affectedArea: string;
  symptoms: string;
  sinceWhen: string;
  frequency: string;
  worseBy: string;
  betterBy: string;
  associatedSymptoms: string;
}

export interface PastDiseaseEntry {
  category: string;
  diseaseName: string;
  treatmentTaken: string;
  yearsAgo: number | null;
  duration: string;
  residualSymptoms: string;
}

export interface FamilyHistoryEntry {
  relation: string;
  disease: string;
  ageOfOnset: number | null;
  causeOfDeath: string;
  isAlive: boolean;
}

export interface PhysicalGeneralsData {
  appetite: string;
  thirst: string;
  foodCravings: string;
  foodAversions: string;
  stool: string;
  urine: string;
  sweat: string;
  sleep: string;
  dreams: string;
  thermalWinter: string;
  thermalSummer: string;
  thermalMonsoon: string;
  menstrualHistory?: string;
}

export interface MentalProfileData {
  nature: string;
  lifeGoal: string;
  anxietyTriggers: string;
  fears: string;
  angerExpression: string;
  sadnessTriggers: string;
  memory: string;
  hobbies: string;
  saddestEvent: string;
  happiestEvent: string;
  unforgettableIncident: string;
  revengeTendency: string;
  cryingNature: string;
  aloneVsCompany: string;
  relationshipImpact: string;
  mentalEffectOfDisease: string;
  socialComfort: string;
  personalityTraits: string[];
}

export interface DeclarationData {
  signature: string; // Base64
  truthConfirmation: boolean;
}

// ─── Full Online Intake ────────────────────────────────

export interface OnlineFormData {
  complaints: ComplaintEntry[];
  pastDiseases: PastDiseaseEntry[];
  familyHistory: FamilyHistoryEntry[];
  physicalGenerals: PhysicalGeneralsData;
  mentalProfile: MentalProfileData;
  declaration: DeclarationData;
}

// ─── API Response Types ────────────────────────────────

export interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  error?: string;
  message?: string;
}

export interface AuthToken {
  token: string;
  user: {
    id: string;
    mobile: string;
    role: Role;
    fullName?: string;
  };
}

// ─── Admin Types ───────────────────────────────────────

export interface PatientListItem {
  id: string;
  fullName: string;
  mobile: string;
  age: number;
  gender: Gender;
  city: string;
  consultationType: ConsultationType;
  lastVisit: string;
  appointmentCount: number;
}

export interface PrescriptionEntry {
  medicineName: string;
  potency: string;
  dosage: string;
  frequency: string;
  duration: string;
  instructions: string;
}

// ─── Disease Categories ────────────────────────────────

export const DISEASE_CATEGORIES = {
  respiratory: ['Asthma', 'Tuberculosis', 'Pneumonia', 'Bronchitis', 'Tonsillitis'],
  skin: ['Measles', 'Chickenpox', 'Psoriasis', 'Eczema', 'Allergy'],
  fever: ['Dengue', 'Malaria', 'Hepatitis'],
  surgery: ['Appendix', 'Hernia', 'Kidney Stone'],
} as const;

export const MEDICAL_HISTORY_OPTIONS = [
  'Diabetes',
  'BP',
  'Thyroid',
  'Asthma',
  'TB',
  'Surgery',
  'None',
] as const;

export const FAMILY_RELATIONS = [
  'Father',
  'Mother',
  'Brother',
  'Sister',
  'Grandfather (Paternal)',
  'Grandmother (Paternal)',
  'Grandfather (Maternal)',
  'Grandmother (Maternal)',
] as const;

export const PERSONALITY_TRAITS = [
  'Introvert',
  'Extrovert',
  'Optimistic',
  'Pessimistic',
  'Perfectionist',
  'Lazy',
  'Anxious',
  'Calm',
  'Sensitive',
  'Aggressive',
  'Stubborn',
  'Flexible',
  'Jealous',
  'Sympathetic',
  'Ambitious',
  'Indecisive',
] as const;
