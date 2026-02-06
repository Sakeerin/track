import React from 'react';
import { useTranslation } from 'react-i18next';
import { ShipmentStatus, TrackingEvent } from '../../types/tracking.types';

interface ProgressBarProps {
  status: ShipmentStatus;
  events: TrackingEvent[];
  locale?: 'th' | 'en';
  className?: string;
}

interface Milestone {
  key: string;
  label: string;
  eventCodes: string[];
  completed: boolean;
  current: boolean;
}

const ProgressBar: React.FC<ProgressBarProps> = ({
  status,
  events,
  locale = 'en',
  className = '',
}) => {
  const { t } = useTranslation();

  // Define the standard milestones for shipment progress
  const getMilestones = (): Milestone[] => {
    const eventCodes = events.map(e => e.eventCode.toLowerCase());
    
    const milestones: Milestone[] = [
      {
        key: 'created',
        label: t('milestone.created', 'Order Created'),
        eventCodes: ['created', 'order_created', 'booked'],
        completed: false,
        current: false,
      },
      {
        key: 'picked_up',
        label: t('milestone.picked_up', 'Picked Up'),
        eventCodes: ['picked_up', 'pickup', 'collected'],
        completed: false,
        current: false,
      },
      {
        key: 'in_transit',
        label: t('milestone.in_transit', 'In Transit'),
        eventCodes: ['in_transit', 'departed', 'on_the_way', 'shipped'],
        completed: false,
        current: false,
      },
      {
        key: 'at_hub',
        label: t('milestone.at_hub', 'At Destination Hub'),
        eventCodes: ['at_hub', 'arrived', 'at_destination', 'sorting'],
        completed: false,
        current: false,
      },
      {
        key: 'out_for_delivery',
        label: t('milestone.out_for_delivery', 'Out for Delivery'),
        eventCodes: ['out_for_delivery', 'delivery', 'on_delivery'],
        completed: false,
        current: false,
      },
      {
        key: 'delivered',
        label: t('milestone.delivered', 'Delivered'),
        eventCodes: ['delivered', 'completed'],
        completed: false,
        current: false,
      },
    ];

    // Mark milestones as completed based on events
    milestones.forEach(milestone => {
      milestone.completed = milestone.eventCodes.some(code => 
        eventCodes.includes(code)
      );
    });

    // Handle special statuses
    if (status === 'delivered') {
      // Mark all milestones as completed for delivered shipments
      milestones.forEach(milestone => {
        milestone.completed = true;
      });
      milestones[milestones.length - 1].current = true;
    } else if (status === 'exception' || status === 'returned' || status === 'cancelled') {
      // For exception states, mark current based on last completed milestone
      const lastCompletedIndex = milestones.map(m => m.completed).lastIndexOf(true);
      if (lastCompletedIndex >= 0) {
        milestones[lastCompletedIndex].current = true;
      }
    } else {
      // For normal flow, mark the next incomplete milestone as current
      const firstIncompleteIndex = milestones.findIndex(m => !m.completed);
      if (firstIncompleteIndex >= 0) {
        milestones[firstIncompleteIndex].current = true;
      } else {
        // All milestones completed but not delivered - mark last as current
        milestones[milestones.length - 1].current = true;
      }
    }

    return milestones;
  };

  const milestones = getMilestones();
  const completedCount = milestones.filter(m => m.completed).length;
  const progressPercentage = (completedCount / milestones.length) * 100;

  // Get milestone icon
  const getMilestoneIcon = (milestone: Milestone): React.ReactNode => {
    const iconClass = "w-4 h-4";
    
    if (milestone.completed) {
      return (
        <svg className={iconClass} fill="currentColor" viewBox="0 0 20 20">
          <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
        </svg>
      );
    } else if (milestone.current) {
      return (
        <div className={`${iconClass} rounded-full border-2 border-current`} />
      );
    } else {
      return (
        <div className={`${iconClass} rounded-full bg-current opacity-30`} />
      );
    }
  };

  // Get milestone styling
  const getMilestoneClass = (milestone: Milestone): string => {
    if (status === 'exception' || status === 'returned' || status === 'cancelled') {
      if (milestone.completed) {
        return 'text-gray-600';
      } else if (milestone.current) {
        return 'text-red-600';
      } else {
        return 'text-gray-400';
      }
    }
    
    if (milestone.completed) {
      return 'text-green-600';
    } else if (milestone.current) {
      return 'text-blue-600';
    } else {
      return 'text-gray-400';
    }
  };

  return (
    <div className={`progress-bar ${className}`}>
      {/* Progress Bar */}
      <div className="relative mb-4">
        <div className="flex items-center justify-between mb-2">
          <span className="text-sm font-medium text-gray-700">
            {t('progress.title', 'Shipment Progress')}
          </span>
          <span className="text-sm text-gray-500">
            {completedCount}/{milestones.length}
          </span>
        </div>
        
        <div className="w-full bg-gray-200 rounded-full h-2">
          <div 
            className={`h-2 rounded-full transition-all duration-300 ${
              status === 'exception' || status === 'returned' || status === 'cancelled'
                ? 'bg-red-500'
                : status === 'delivered'
                ? 'bg-green-500'
                : 'bg-blue-500'
            }`}
            style={{ width: `${progressPercentage}%` }}
            role="progressbar"
            aria-valuenow={progressPercentage}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-label={t('progress.ariaLabel', 'Shipment progress: {{percent}}% complete', { 
              percent: Math.round(progressPercentage) 
            })}
          />
        </div>
      </div>

      {/* Milestones */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
        {milestones.map((milestone, index) => (
          <div 
            key={milestone.key}
            className={`flex flex-col items-center text-center p-2 rounded-lg transition-colors duration-200 ${
              milestone.current ? 'bg-blue-50' : ''
            }`}
          >
            <div className={`mb-2 ${getMilestoneClass(milestone)}`}>
              {getMilestoneIcon(milestone)}
            </div>
            <span className={`text-xs font-medium ${getMilestoneClass(milestone)}`}>
              {milestone.label}
            </span>
          </div>
        ))}
      </div>

      {/* Status Message */}
      {(status === 'exception' || status === 'returned' || status === 'cancelled') && (
        <div className="mt-3 p-2 bg-red-50 border border-red-200 rounded-md">
          <p className="text-sm text-red-800">
            {status === 'exception' && t('progress.exception', 'Shipment has encountered an issue')}
            {status === 'returned' && t('progress.returned', 'Shipment has been returned')}
            {status === 'cancelled' && t('progress.cancelled', 'Shipment has been cancelled')}
          </p>
        </div>
      )}
    </div>
  );
};

export default ProgressBar;