export interface Location {
  id: string;
  name: string;
  nameEn?: string;
  nameTh?: string;
  latitude?: number;
  longitude?: number;
  address?: string;
  timezone?: string;
}

export interface Facility extends Location {
  code: string;
  facilityType: string;
  active: boolean;
}

export interface TrackingEvent {
  id: string;
  eventTime: Date;
  eventCode: string;
  description: string;
  descriptionEn?: string;
  descriptionTh?: string;
  location?: Location;
  facility?: Facility;
  remarks?: string;
  source: string;
}

export interface Exception {
  id: string;
  type: string;
  message: string;
  messageEn?: string;
  messageTh?: string;
  guidance?: string;
  guidanceEn?: string;
  guidanceTh?: string;
  severity: 'low' | 'medium' | 'high' | 'critical';
  resolved: boolean;
  createdAt: Date;
  resolvedAt?: Date;
}

export type ShipmentStatus = 
  | 'created'
  | 'picked_up'
  | 'in_transit'
  | 'at_hub'
  | 'out_for_delivery'
  | 'delivered'
  | 'exception'
  | 'returned'
  | 'cancelled';

export interface Shipment {
  id: string;
  trackingNumber: string;
  referenceNumber?: string;
  status: ShipmentStatus;
  serviceType: string;
  origin?: Facility;
  destination?: Facility;
  currentLocation?: Location;
  estimatedDelivery?: Date;
  events: TrackingEvent[];
  exceptions: Exception[];
  createdAt: Date;
  updatedAt: Date;
}

export interface TrackingRequest {
  trackingNumbers: string[];
}

export interface TrackingResponse {
  success: boolean;
  data: Shipment[];
  errors: TrackingError[];
  meta: {
    total: number;
    found: number;
    notFound: number;
  };
}

export interface TrackingError {
  trackingNumber: string;
  code: string;
  message: string;
  messageEn?: string;
  messageTh?: string;
}

export interface ValidationError {
  field: string;
  message: string;
  code: string;
}

export interface TrackingFormData {
  input: string;
  trackingNumbers: string[];
}

export interface TrackingFormState {
  input: string;
  validatedNumbers: string[];
  errors: ValidationError[];
  isValidating: boolean;
  isSubmitting: boolean;
}