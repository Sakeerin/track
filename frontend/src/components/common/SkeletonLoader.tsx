import React from 'react';

interface SkeletonLoaderProps {
  className?: string;
  width?: string;
  height?: string;
  rounded?: boolean;
}

const SkeletonLoader: React.FC<SkeletonLoaderProps> = ({
  className = '',
  width = 'w-full',
  height = 'h-4',
  rounded = false,
}) => {
  return (
    <div
      className={`
        animate-pulse bg-gray-200 
        ${width} ${height} 
        ${rounded ? 'rounded-full' : 'rounded'}
        ${className}
      `}
      role="status"
      aria-label="Loading..."
    />
  );
};

// Predefined skeleton components for common use cases
export const SkeletonText: React.FC<{ lines?: number; className?: string }> = ({ 
  lines = 1, 
  className = '' 
}) => (
  <div className={`space-y-2 ${className}`}>
    {Array.from({ length: lines }).map((_, index) => (
      <SkeletonLoader 
        key={index}
        width={index === lines - 1 ? 'w-3/4' : 'w-full'}
        height="h-4"
      />
    ))}
  </div>
);

export const SkeletonCard: React.FC<{ className?: string }> = ({ className = '' }) => (
  <div className={`bg-white rounded-lg shadow-md p-6 ${className}`}>
    <div className="flex items-start justify-between mb-4">
      <div className="space-y-2">
        <SkeletonLoader width="w-32" height="h-6" />
        <SkeletonLoader width="w-24" height="h-4" />
      </div>
      <SkeletonLoader width="w-20" height="h-6" rounded />
    </div>
    
    <div className="space-y-3">
      <SkeletonLoader width="w-full" height="h-2" rounded />
      <div className="flex justify-between">
        <SkeletonLoader width="w-20" height="h-4" />
        <SkeletonLoader width="w-24" height="h-4" />
      </div>
    </div>
    
    <div className="mt-4 pt-4 border-t border-gray-200">
      <SkeletonText lines={3} />
    </div>
  </div>
);

export const SkeletonTable: React.FC<{ 
  rows?: number; 
  columns?: number; 
  className?: string 
}> = ({ 
  rows = 5, 
  columns = 4, 
  className = '' 
}) => (
  <div className={`bg-white rounded-lg shadow-md overflow-hidden ${className}`}>
    {/* Table header */}
    <div className="bg-gray-50 px-6 py-3 border-b border-gray-200">
      <div className="flex space-x-4">
        {Array.from({ length: columns }).map((_, index) => (
          <SkeletonLoader key={index} width="w-24" height="h-4" />
        ))}
      </div>
    </div>
    
    {/* Table rows */}
    <div className="divide-y divide-gray-200">
      {Array.from({ length: rows }).map((_, rowIndex) => (
        <div key={rowIndex} className="px-6 py-4">
          <div className="flex space-x-4">
            {Array.from({ length: columns }).map((_, colIndex) => (
              <SkeletonLoader 
                key={colIndex} 
                width={colIndex === 0 ? 'w-32' : 'w-20'} 
                height="h-4" 
              />
            ))}
          </div>
        </div>
      ))}
    </div>
  </div>
);

export default SkeletonLoader;