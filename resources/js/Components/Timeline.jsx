export function Timeline({ children }) {
    return <div className="qdn-timeline">{children}</div>;
}

export function TimelineItem({ icon: Icon, title, children }) {
    return (
        <div className="qdn-timeline-item">
            <div className="qdn-timeline-icon">{Icon && <Icon size={18} />}</div>
            <div className="qdn-timeline-content">
                {title && <h3 className="qdn-timeline-title">{title}</h3>}
                {children}
            </div>
        </div>
    );
}
